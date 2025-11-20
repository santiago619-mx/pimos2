<?php

namespace Tests\Feature\Api;

use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Symfony\Component\HttpFoundation\Response;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pruebas de integración para los endpoints de la API de Productos.
 * Asegura que solo el Administrador pueda realizar operaciones de escritura (CRUD).
 */
class ProductoTest extends TestCase
{
    use RefreshDatabase, WithFaker; 

    // Ejecuta el seeder de roles antes de cada prueba
    protected function setUp(): void
    {
        parent::setUp();
        // Asumiendo que el Seeder de Roles existe y se llama 'RolSeeder'
        $this->artisan('db:seed', ['--class' => 'RolSeeder']); 
    }

    // --- TESTS DE LECTURA (PÚBLICOS PARA USUARIOS AUTENTICADOS) ---

    public function test_puede_listar_productos_todos_los_usuarios_autenticados_index()
    {
        // Actuar como un usuario simple
        Sanctum::actingAs(User::factory()->create()->assignRole('Usuario'));
        
        // Crear datos de prueba
        Producto::factory(5)->create();

        $response = $this->getJson('/api/productos');

        $response->assertStatus(Response::HTTP_OK)
                ->assertJsonCount(5, 'data')
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'tipo',
                            'atributos' => [
                                'nombre',
                                'sabor',
                                'precio',
                                'stock_actual', // Viene de ProductoCollection
                            ]
                        ]
                    ]
                ]);
    }

    public function test_puede_mostrar_un_producto_especifico_todos_los_usuarios_autenticados_show()
    {
        Sanctum::actingAs(User::factory()->create()->assignRole('Usuario'));
        
        // Crear un producto específico
        $producto = Producto::factory()->create();

        $response = $this->getJson("/api/productos/{$producto->id}");

        $response->assertStatus(Response::HTTP_OK)
                ->assertJsonFragment(['id' => $producto->id])
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'tipo',
                        'atributos' => ['nombre', 'sabor', 'tamano', 'precio'],
                        'relaciones' => ['inventario']
                    ]
                ]);
    }


    // --- TESTS DE CREACIÓN (SOLO ADMINISTRADOR) ---

    public function test_puede_crear_un_producto_nuevo_solo_el_administrador_store()
    {
        // Actuar como Administrador
        $admin = Sanctum::actingAs(User::factory()->create()->assignRole('Administrador'));

        $data = [
            'nombre_gomita' => $this->faker->unique()->word . ' Gummi',
            'sabor' => $this->faker->colorName,
            'tamano' => 'Mediano',
            'precio' => $this->faker->randomFloat(2, 0.5, 50),
            'cantidad_existencias' => 100 // Dato para crear el Inventario
        ];

        $response = $this->postJson('/api/productos', $data);
        
        // Verificar que se haya creado
        $response->assertStatus(Response::HTTP_CREATED)
                ->assertJsonFragment(['nombre' => $data['nombre_gomita']]);

        // Verificar que el producto y su stock inicial se hayan guardado en la DB
        $this->assertDatabaseHas('productos', ['nombre_gomita' => $data['nombre_gomita']]);
        $this->assertDatabaseHas('inventarios', ['producto_id' => $response['id'], 'cantidad_existencias' => 100]);
    }

    public function test_usuario_regular_no_puede_crear_un_producto_store_forbidden()
    {
        // Actuar como Usuario regular
        Sanctum::actingAs(User::factory()->create()->assignRole('Usuario'));

        $data = Producto::factory()->make()->toArray();
        $response = $this->postJson('/api/productos', $data);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }


    // --- TESTS DE ACTUALIZACIÓN (SOLO ADMINISTRADOR) ---

    public function test_puede_actualizar_un_producto_solo_el_administrador_update()
    {
        $admin = Sanctum::actingAs(User::factory()->create()->assignRole('Administrador'));
        $producto = Producto::factory()->create();

        $data = [
            'nombre_gomita' => 'Nuevo Nombre Editado',
            'precio' => 99.99,
        ];

        $response = $this->putJson("/api/productos/{$producto->id}", $data);

        $response->assertStatus(Response::HTTP_OK);
        
        // Verificar que el registro se haya actualizado
        $this->assertDatabaseHas('productos', [
            'id' => $producto->id,
            'nombre_gomita' => 'Nuevo Nombre Editado',
            'precio' => 99.99,
        ]);
    }

    public function test_usuario_regular_no_puede_actualizar_un_producto_update_forbidden()
    {
        Sanctum::actingAs(User::factory()->create()->assignRole('Usuario'));
        $producto = Producto::factory()->create();
        
        $response = $this->putJson("/api/productos/{$producto->id}", ['precio' => 1.00]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }


    // --- TESTS DE ELIMINACIÓN (SOLO ADMINISTRADOR) ---

    public function test_puede_eliminar_un_producto_solo_el_administrador_destroy()
    {
        Sanctum::actingAs(User::factory()->create()->assignRole('Administrador'));
        
        // Crear un producto con su relación de inventario
        $producto = Producto::factory()->hasInventario(1)->create();

        $response = $this->deleteJson("/api/productos/{$producto->id}");

        $response->assertStatus(Response::HTTP_NO_CONTENT); 

        // Verifica que el producto y su inventario asociado hayan desaparecido
        $this->assertDatabaseMissing('productos', ['id' => $producto->id]);
        $this->assertDatabaseMissing('inventarios', ['producto_id' => $producto->id]); 
    }

    public function test_usuario_regular_no_puede_eliminar_un_producto_destroy_forbidden()
    {
        Sanctum::actingAs(User::factory()->create()->assignRole('Usuario'));
        $producto = Producto::factory()->create();

        $response = $this->deleteJson("/api/productos/{$producto->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        $this->assertDatabaseHas('productos', ['id' => $producto->id]); // Asegura que no se eliminó
    }
}