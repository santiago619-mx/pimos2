<?php

namespace Tests\Feature\Api;

use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Symfony\Component\HttpFoundation\Response;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pruebas de integración para los endpoints de la API de Pedidos.
 * Asegura la visibilidad solo para el dueño del pedido o el Administrador.
 */
class PedidoTest extends TestCase
{
    use RefreshDatabase, WithFaker; 

    protected function setUp(): void
    {
        parent::setUp();
        // Carga los seeders necesarios antes de cada prueba
        $this->artisan('db:seed', ['--class' => 'RolSeeder']);
    }

    // --- TESTS DE LECTURA ---

    public function test_puede_listar_todos_los_pedidos_solo_el_administrador_index()
    {
        // Actuar como Administrador
        Sanctum::actingAs(User::factory()->create()->assignRole('Administrador'));
        
        Pedido::factory(5)->create();

        $response = $this->getJson('/api/pedidos');

        $response->assertStatus(Response::HTTP_OK)
                ->assertJsonCount(5, 'data');
    }

    public function test_usuario_regular_no_puede_acceder_al_listado_global_de_pedidos_index_forbidden()
    {
        // Actuar como Usuario simple
        Sanctum::actingAs(User::factory()->create()->assignRole('Usuario'));

        $response = $this->getJson('/api/pedidos');

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }


    public function test_puede_ver_su_propio_pedido_show_owner()
    {
        $owner = User::factory()->create()->assignRole('Usuario');
        Sanctum::actingAs($owner);
        
        // Crear un pedido a nombre del dueño
        $pedido = Pedido::factory()->create(['user_id' => $owner->id]);

        $response = $this->getJson("/api/pedidos/{$pedido->id}");

        $response->assertStatus(Response::HTTP_OK)
                ->assertJsonFragment(['pedido_id' => $pedido->id])
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'tipo',
                        'atributos' => ['total', 'estado', 'fecha_pedido'],
                        'relaciones' => ['usuario', 'detalles']
                    ]
                ]);
    }

    public function test_usuario_no_puede_ver_pedido_de_otro_usuario_show_other_forbidden()
    {
        $owner = User::factory()->create();
        $otroUsuario = User::factory()->create()->assignRole('Usuario');
        Sanctum::actingAs($otroUsuario); // Actúa como el otro usuario
        
        // Pedido creado por el dueño
        $pedido = Pedido::factory()->create(['user_id' => $owner->id]);

        $response = $this->getJson("/api/pedidos/{$pedido->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }


    // --- TESTS DE CREACIÓN (Cualquier usuario autenticado) ---

    public function test_usuario_puede_crear_un_pedido_valido_store()
    {
        $usuario = Sanctum::actingAs(User::factory()->create()->assignRole('Usuario'));
        
        // Crear un producto con stock
        $producto = Producto::factory()->hasInventario(1, ['cantidad_existencias' => 50])->create(['precio' => 10.00]);

        $data = [
            // No es necesario enviar user_id, se toma del usuario autenticado
            'detalles' => [
                [
                    'producto_id' => $producto->id,
                    'cantidad' => 5, 
                    'precio_unitario' => 10.00,
                ]
            ]
        ];

        $response = $this->postJson('/api/pedidos', $data);

        // Se espera 201 Created y que el controlador haya calculado el total (5 * 10 = 50)
        $response->assertStatus(Response::HTTP_CREATED)
                ->assertJsonFragment(['total' => 50.00])
                ->assertJsonFragment(['estado' => 'pendiente']);

        // Verificar que se haya reducido el stock (el controlador lo hace)
        $this->assertDatabaseHas('inventarios', ['producto_id' => $producto->id, 'cantidad_existencias' => 45]);
    }

    public function test_crear_un_pedido_falla_si_no_hay_suficiente_stock()
    {
        $usuario = Sanctum::actingAs(User::factory()->create()->assignRole('Usuario'));
        
        // Producto con poco stock
        $producto = Producto::factory()->hasInventario(1, ['cantidad_existencias' => 5])->create();

        $data = [
            'detalles' => [
                [
                    'producto_id' => $producto->id,
                    'cantidad' => 10, // Pide más de 5
                ]
            ]
        ];

        $response = $this->postJson('/api/pedidos', $data);

        // El controlador debe retornar 422 con un error de validación personalizado.
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->assertJsonValidationErrors('detalles.0.cantidad'); 
    }


    // --- TESTS DE ACTUALIZACIÓN (Dueño/Admin) ---

    public function test_puede_actualizar_su_propio_pedido_el_dueño_del_pedido_update_owner()
    {
        $owner = User::factory()->create()->assignRole('Usuario');
        Sanctum::actingAs($owner);
        
        $pedido = Pedido::factory()->create(['user_id' => $owner->id, 'estado' => 'pendiente']);

        // El dueño solo intenta cambiar el estado (ej: cancelar)
        $data = ['estado' => 'cancelado'];

        $response = $this->putJson("/api/pedidos/{$pedido->id}", $data);

        $response->assertStatus(Response::HTTP_OK)
                ->assertJsonFragment(['estado' => 'cancelado']);

        $this->assertDatabaseHas('pedidos', ['id' => $pedido->id, 'estado' => 'cancelado']);
    }

    public function test_usuario_no_puede_actualizar_pedido_de_otro_usuario_update_other_forbidden()
    {
        $owner = User::factory()->create();
        $otroUsuario = User::factory()->create()->assignRole('Usuario');
        Sanctum::actingAs($otroUsuario);
        
        $pedido = Pedido::factory()->create(['user_id' => $owner->id]);

        $response = $this->putJson("/api/pedidos/{$pedido->id}", ['estado' => 'cancelado']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        $this->assertDatabaseHas('pedidos', ['id' => $pedido->id, 'estado' => $pedido->estado]); // Sin cambios
    }


    // --- TESTS DE ELIMINACIÓN (Dueño/Admin) ---

    public function test_puede_eliminar_un_pedido_solo_el_administrador_destroy()
    {
        Sanctum::actingAs(User::factory()->create()->assignRole('Administrador'));
        
        // Crear un pedido
        $pedido = Pedido::factory()->create();

        $response = $this->deleteJson("/api/pedidos/{$pedido->id}");

        $response->assertStatus(Response::HTTP_NO_CONTENT); 
        $this->assertDatabaseMissing('pedidos', ['id' => $pedido->id]);
    }

    public function test_puede_eliminar_su_propio_pedido_el_dueño_del_pedido_destroy_owner()
    {
        $owner = User::factory()->create()->assignRole('Usuario');
        Sanctum::actingAs($owner);
        
        $pedido = Pedido::factory()->create(['user_id' => $owner->id]);

        $response = $this->deleteJson("/api/pedidos/{$pedido->id}");

        $response->assertStatus(Response::HTTP_NO_CONTENT); 
        $this->assertDatabaseMissing('pedidos', ['id' => $pedido->id]);
    }
}