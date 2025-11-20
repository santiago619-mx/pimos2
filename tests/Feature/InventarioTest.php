<?php

namespace Tests\Feature\Api;

use App\Models\Inventario;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Symfony\Component\HttpFoundation\Response;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pruebas de integración para el Inventario API.
 * Acceso restringido exclusivamente a Administradores.
 */
class InventarioTest extends TestCase
{
    use RefreshDatabase, WithFaker; 

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolSeeder']);
    }

    // --- TESTS DE LECTURA (SOLO ADMINISTRADOR) ---

    public function test_puede_listar_inventario_solo_el_administrador_index()
    {
        // Actuar como Administrador
        Sanctum::actingAs(User::factory()->create()->assignRole('Administrador'));
        
        // Crear datos de prueba (Inventario se crea a través de Producto)
        Producto::factory(3)->hasInventario(1, ['cantidad_existencias' => 50])->create();

        $response = $this->getJson('/api/inventario');

        $response->assertStatus(Response::HTTP_OK)
                ->assertJsonCount(3, 'data')
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'tipo',
                            'atributos' => ['cantidad_existencias'],
                            'relaciones' => ['producto']
                        ]
                    ]
                ]);
    }

    public function test_usuario_regular_no_puede_acceder_al_listado_de_inventario_index_forbidden()
    {
        // Actuar como Usuario simple
        Sanctum::actingAs(User::factory()->create()->assignRole('Usuario'));

        $response = $this->getJson('/api/inventario');

        $response->assertStatus(Response::HTTP_FORBIDDEN); // Espera 403 Forbidden
    }

    public function test_puede_mostrar_un_registro_de_inventario_solo_el_administrador_show()
    {
        Sanctum::actingAs(User::factory()->create()->assignRole('Administrador'));
        
        // Crear un registro de inventario (asociado a un producto)
        $inventario = Inventario::factory()->create();

        $response = $this->getJson("/api/inventario/{$inventario->inventario_id}");

        $response->assertStatus(Response::HTTP_OK)
                ->assertJsonFragment(['id' => $inventario->inventario_id])
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'tipo',
                        'atributos' => ['cantidad_existencias'],
                    ]
                ]);
    }


    // --- TESTS DE CREACIÓN (SOLO ADMINISTRADOR) ---

    public function test_puede_crear_un_registro_de_inventario_solo_el_administrador_store()
    {
        Sanctum::actingAs(User::factory()->create()->assignRole('Administrador'));

        // Crear un producto que AÚN NO tiene inventario
        $producto = Producto::factory()->create(); 

        $data = [
            'producto_id' => $producto->id,
            'cantidad_existencias' => 200,
        ];

        $response = $this->postJson('/api/inventario', $data);
        
        $response->assertStatus(Response::HTTP_CREATED);

        $this->assertDatabaseHas('inventarios', [
            'producto_id' => $producto->id,
            'cantidad_existencias' => 200
        ]);
    }

    public function test_intento_de_crear_inventario_con_producto_duplicado_falla()
    {
        Sanctum::actingAs(User::factory()->create()->assignRole('Administrador'));

        // Crear un registro de inventario existente
        $inventarioExistente = Inventario::factory()->create(['cantidad_existencias' => 10]);

        $data = [
            'producto_id' => $inventarioExistente->producto_id, // Usar el mismo ID
            'cantidad_existencias' => 50,
        ];

        $response = $this->postJson('/api/inventario', $data);
        
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY) // Espera 422
                ->assertJsonValidationErrors('producto_id'); // Verifica el error de unicidad
    }


    // --- TESTS DE ACTUALIZACIÓN (SOLO ADMINISTRADOR) ---

    public function test_puede_actualizar_la_cantidad_solo_el_administrador_update()
    {
        Sanctum::actingAs(User::factory()->create()->assignRole('Administrador'));
        $inventario = Inventario::factory()->create(['cantidad_existencias' => 10]);

        $data = ['cantidad_existencias' => 500];

        $response = $this->putJson("/api/inventario/{$inventario->inventario_id}", $data);

        $response->assertStatus(Response::HTTP_OK);
        
        $this->assertDatabaseHas('inventarios', [
            'inventario_id' => $inventario->inventario_id,
            'cantidad_existencias' => 500,
        ]);
    }


    // --- TESTS DE ELIMINACIÓN (SOLO ADMINISTRADOR) ---

    public function test_puede_eliminar_un_registro_solo_el_administrador_destroy()
    {
        Sanctum::actingAs(User::factory()->create()->assignRole('Administrador'));
        $inventario = Inventario::factory()->create();

        $response = $this->deleteJson("/api/inventario/{$inventario->inventario_id}");

        $response->assertStatus(Response::HTTP_NO_CONTENT); 

        $this->assertDatabaseMissing('inventarios', ['inventario_id' => $inventario->inventario_id]);
    }

    public function test_usuario_regular_no_puede_eliminar_inventario_destroy_forbidden()
    {
        Sanctum::actingAs(User::factory()->create()->assignRole('Usuario'));
        $inventario = Inventario::factory()->create();

        $response = $this->deleteJson("/api/inventario/{$inventario->inventario_id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        $this->assertDatabaseHas('inventarios', ['inventario_id' => $inventario->inventario_id]);
    }
}