<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Validation\Rule;
use Tests\TestCase;


use App\Models\Producto;
//use App\Models\Inventario;
//use App\Models\Pedido;
//use App\Models\DetallePedido;
use App\Models\User;




uses(RefreshDatabase::class); // Resetea la base de datos después de cada prueba
uses(\Illuminate\Foundation\Testing\WithFaker::class); // Habilita Faker para datos aleatorios

// Antes de empezar, asume que existe un seeder de roles.
beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolSeeder']);
});

// --- TESTS DE LECTURA (PÚBLICOS PARA USUARIOS AUTENTICADOS) ---

test('puede_listar_productos_todos_los_usuarios_autenticados (index)', function () {
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
});

test('puede_mostrar_un_producto_especifico_todos_los_usuarios_autenticados (show)', function () {
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
                     'relaciones' => ['inventario'] // Verifica que la estructura de relaciones esté presente
                 ]
             ]);
});


// --- TESTS DE CREACIÓN (SOLO ADMINISTRADOR) ---

test('puede_crear_un_producto_nuevo_solo_el_administrador (store)', function () {
    // Actuar como Administrador
    $admin = Sanctum::actingAs(User::factory()->create()->assignRole('Administrador'));

    $data = [
        'nombre_gomita' => $this->faker->unique()->word . ' Gummi',
        'sabor' => $this->faker->colorName,
        'tamano' => 'Mediano',
        'precio' => $this->faker->randomFloat(2, 0.5, 50),
        'cantidad_existencias' => 100 // Opcional en el Request
    ];

    $response = $this->postJson('/api/productos', $data);
    
    // Verificar que se haya creado
    $response->assertStatus(Response::HTTP_CREATED)
             ->assertJsonFragment(['nombre' => $data['nombre_gomita']]);

    // Verificar que el producto y su stock inicial se hayan guardado en la DB
    $this->assertDatabaseHas('productos', ['nombre_gomita' => $data['nombre_gomita']]);
    $this->assertDatabaseHas('inventarios', ['cantidad_existencias' => 100]);
});

test('usuario_regular_no_puede_crear_un_producto (store_forbidden)', function () {
    // Actuar como Usuario regular
    Sanctum::actingAs(User::factory()->create()->assignRole('Usuario'));

    $data = Producto::factory()->make()->toArray();
    $response = $this->postJson('/api/productos', $data);

    $response->assertStatus(Response::HTTP_FORBIDDEN); // Espera 403 Forbidden
});


// --- TESTS DE ACTUALIZACIÓN (SOLO ADMINISTRADOR) ---

test('puede_actualizar_un_producto_solo_el_administrador (update)', function () {
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
});

test('usuario_regular_no_puede_actualizar_un_producto (update_forbidden)', function () {
    Sanctum::actingAs(User::factory()->create()->assignRole('Usuario'));
    $producto = Producto::factory()->create();
    
    $response = $this->putJson("/api/productos/{$producto->id}", ['precio' => 1.00]);

    $response->assertStatus(Response::HTTP_FORBIDDEN);
});


// --- TESTS DE ELIMINACIÓN (SOLO ADMINISTRADOR) ---

test('puede_eliminar_un_producto_solo_el_administrador (destroy)', function () {
    Sanctum::actingAs(User::factory()->create()->assignRole('Administrador'));
    
    // Crear un producto con su relación de inventario
    $producto = Producto::factory()->hasInventario(1)->create();

    $response = $this->deleteJson("/api/productos/{$producto->id}");

    $response->assertStatus(Response::HTTP_NO_CONTENT); // Espera 204 No Content

    // Verifica que el producto y su inventario asociado (si existe) hayan desaparecido
    $this->assertDatabaseMissing('productos', ['id' => $producto->id]);
    $this->assertDatabaseMissing('inventarios', ['producto_id' => $producto->id]); 
});

test('usuario_regular_no_puede_eliminar_un_producto (destroy_forbidden)', function () {
    Sanctum::actingAs(User::factory()->create()->assignRole('Usuario'));
    $producto = Producto::factory()->create();

    $response = $this->deleteJson("/api/productos/{$producto->id}");

    $response->assertStatus(Response::HTTP_FORBIDDEN);
    $this->assertDatabaseHas('productos', ['id' => $producto->id]); // Asegura que no se eliminó
});
