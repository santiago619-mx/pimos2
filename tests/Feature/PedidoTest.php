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
 * Asegura la visibilidad y manipulación solo para el dueño del pedido o el Administrador.
 */
class PedidoTest extends TestCase
{
    use RefreshDatabase, WithFaker; 

    protected function setUp(): void
    {
        parent::setUp();
        // Carga los seeders necesarios antes de cada prueba (asume RolSeeder existe)
        $this->artisan('db:seed', ['--class' => 'RolSeeder']);
    }

    // ----------------------------------------------------------------------------------------------------------------------
    // --- TESTS DE LECTURA (INDEX, SHOW) ---
    // ----------------------------------------------------------------------------------------------------------------------

    /** * Test para verificar que solo el administrador puede listar todos los pedidos (index).
     */
    public function test_puede_listar_todos_los_pedidos_solo_el_administrador_index()
    {
        // Crear y asignar rol de Administrador.
        $admin = User::factory()->create()->assignRole('Administrador');
        
        // CRÍTICO: fresh() asegura que el usuario autenticado tiene el rol 'Administrador' cargado desde la BD.
        Sanctum::actingAs($admin->fresh());
        
        Pedido::factory(5)->create();

        $response = $this->getJson('/api/pedidos');

        $response->assertStatus(Response::HTTP_OK)
                ->assertJsonCount(5, 'data');
    }

    /** * Test para verificar que un usuario regular no puede ver el listado global de pedidos.
     */
    public function test_usuario_regular_no_puede_acceder_al_listado_global_de_pedidos_index_forbidden()
    {
        // Actuar como Usuario simple
        Sanctum::actingAs(User::factory()->create()->assignRole('Usuario'));

        $response = $this->getJson('/api/pedidos');

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /** * Test para verificar que un usuario puede ver un pedido que le pertenece (show).
     */
    public function test_puede_ver_su_propio_pedido_show_owner()
    {
        $owner = User::factory()->create()->assignRole('Usuario');
        Sanctum::actingAs($owner);
        
        $pedido = Pedido::factory()->create(['user_id' => $owner->id]);

        $response = $this->getJson("/api/pedidos/{$pedido->id}");

        $response->assertStatus(Response::HTTP_OK)
                ->assertJsonFragment(['pedido_id' => $pedido->id]);
    }

    /** * Test para verificar que un usuario no puede ver un pedido que no le pertenece.
     */
    public function test_usuario_no_puede_ver_pedido_de_otro_usuario_show_other_forbidden()
    {
        $owner = User::factory()->create();
        $otroUsuario = User::factory()->create()->assignRole('Usuario');
        Sanctum::actingAs($otroUsuario);
        
        $pedido = Pedido::factory()->create(['user_id' => $owner->id]);

        $response = $this->getJson("/api/pedidos/{$pedido->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }


    // ----------------------------------------------------------------------------------------------------------------------
    // --- TESTS DE CREACIÓN (STORE) ---
    // ----------------------------------------------------------------------------------------------------------------------

    /** * Test para verificar que cualquier usuario autenticado puede crear un pedido válido.
     */
    public function test_usuario_puede_crear_un_pedido_valido_store()
    {
        $usuario = User::factory()->create()->assignRole('Usuario');
        Sanctum::actingAs($usuario);
        
        // Crear un producto con stock
        $producto = Producto::factory()->hasInventario(1, ['cantidad_existencias' => 50])->create(['precio' => 10.00]);

        $data = [
            'detalles' => [
                [
                    'producto_id' => $producto->id,
                    'cantidad' => 5, 
                    'precio_unitario' => 10.00,
                ]
            ]
        ];

        $response = $this->postJson('/api/pedidos', $data);

        $response->assertStatus(Response::HTTP_CREATED)
                ->assertJsonFragment(['total' => 50.00]);
    }

    /** * Test para verificar que la creación de un pedido falla si no hay suficiente stock.
     */
    public function test_crear_un_pedido_falla_si_no_hay_suficiente_stock()
    {
        $usuario = User::factory()->create()->assignRole('Usuario');
        Sanctum::actingAs($usuario);
        
        $producto = Producto::factory()->hasInventario(1, ['cantidad_existencias' => 5])->create();

        $data = [
            'detalles' => [
                ['producto_id' => $producto->id, 'cantidad' => 10] // Pide más de 5
            ]
        ];

        $response = $this->postJson('/api/pedidos', $data);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->assertJsonValidationErrors('detalles.0.cantidad'); 
    }


    // ----------------------------------------------------------------------------------------------------------------------
    // --- TESTS DE ACTUALIZACIÓN (UPDATE) ---
    // ----------------------------------------------------------------------------------------------------------------------

    /** * Test para verificar que el dueño de un pedido puede actualizarlo.
     */
    public function test_puede_actualizar_su_propio_pedido_el_dueño_del_pedido_update_owner()
    {
        $owner = User::factory()->create()->assignRole('Usuario');
        // CRÍTICO: Usar fresh() asegura que el modelo $owner tiene su 'id' correctamente mapeado con el Pedido.
        Sanctum::actingAs($owner->fresh()); 
        
        $pedido = Pedido::factory()->create(['user_id' => $owner->id, 'estado' => 'pendiente']);

        $data = ['estado' => 'cancelado'];

        $response = $this->putJson("/api/pedidos/{$pedido->id}", $data);

        $response->assertStatus(Response::HTTP_OK)
                ->assertJsonFragment(['estado' => 'cancelado']);

        $this->assertDatabaseHas('pedidos', ['id' => $pedido->id, 'estado' => 'cancelado']);
    }

    /** * Test para verificar que un usuario no puede actualizar un pedido que no le pertenece.
     */
    public function test_usuario_no_puede_actualizar_pedido_de_otro_usuario_update_other_forbidden()
    {
        $owner = User::factory()->create();
        $otroUsuario = User::factory()->create()->assignRole('Usuario');
        Sanctum::actingAs($otroUsuario);
        
        $pedido = Pedido::factory()->create(['user_id' => $owner->id]);

        $response = $this->putJson("/api/pedidos/{$pedido->id}", ['estado' => 'cancelado']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }


    // ----------------------------------------------------------------------------------------------------------------------
    // --- TESTS DE ELIMINACIÓN (DESTROY) ---
    // ----------------------------------------------------------------------------------------------------------------------

    /** * Test para verificar que solo el administrador puede eliminar un pedido.
     */
    public function test_puede_eliminar_un_pedido_solo_el_administrador_destroy()
    {
        $admin = User::factory()->create()->assignRole('Administrador');
        // CRÍTICO: Usar fresh() para asegurar que el rol de Administrador está cargado.
        Sanctum::actingAs($admin->fresh());
        
        $pedido = Pedido::factory()->create();

        $response = $this->deleteJson("/api/pedidos/{$pedido->id}");

        $response->assertStatus(Response::HTTP_NO_CONTENT); 
        $this->assertDatabaseMissing('pedidos', ['id' => $pedido->id]);
    }

    /** * Test para verificar que el dueño de un pedido puede eliminarlo.
     */
    public function test_puede_eliminar_su_propio_pedido_el_dueño_del_pedido_destroy_owner()
    {
        $owner = User::factory()->create()->assignRole('Usuario');
        // CRÍTICO: Usar fresh() para asegurar que el modelo $owner tiene su 'id' correcto.
        Sanctum::actingAs($owner->fresh());
        
        $pedido = Pedido::factory()->create(['user_id' => $owner->id]);

        $response = $this->deleteJson("/api/pedidos/{$pedido->id}");

        $response->assertStatus(Response::HTTP_NO_CONTENT); 
        $this->assertDatabaseMissing('pedidos', ['id' => $pedido->id]);
    }
}
//aqui