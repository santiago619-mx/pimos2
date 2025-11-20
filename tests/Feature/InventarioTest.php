<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Validation\Rule;
use Tests\TestCase;


use App\Models\Inventario;
use App\Models\Producto;
//use App\Models\Pedido;
//use App\Models\DetallePedido;
use App\Models\User;


uses(RefreshDatabase::class); 
uses(\Illuminate\Foundation\Testing\WithFaker::class); 

// El inventario solo debe ser accesible por el Administrador según la Política.
beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolSeeder']);
});

// --- TESTS DE LECTURA (SOLO ADMINISTRADOR) ---

test('puede_listar_inventario_solo_el_administrador (index)', function () {
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
});

test('usuario_regular_no_puede_acceder_al_listado_de_inventario (index_forbidden)', function () {
    // Actuar como Usuario simple
    Sanctum::actingAs(User::factory()->create()->assignRole('Usuario'));

    $response = $this->getJson('/api/inventario');

    $response->assertStatus(Response::HTTP_FORBIDDEN); // Espera 403 Forbidden
});

test('puede_mostrar_un_registro_de_inventario_solo_el_administrador (show)', function () {
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
});


// --- TESTS DE CREACIÓN (SOLO ADMINISTRADOR) ---

test('puede_crear_un_registro_de_inventario_solo_el_administrador (store)', function () {
    Sanctum::actingAs(User::factory()->create()->assignRole('Administrador'));

    // Crear un producto que AÚN NO tiene inventario
    $producto = Producto::factory()->create(); 

    $data = [
        'producto_id' => $producto->id,
        'cantidad_existencias' => 200,
    ];

    $response = $this->postJson('/api/inventario', $data);
    
    $response->assertStatus(Response::HTTP_CREATED);

    // Verificar unicidad: El Request lo debe forzar
    $this->assertDatabaseHas('inventarios', [
        'producto_id' => $producto->id,
        'cantidad_existencias' => 200
    ]);
});

test('intento_de_crear_inventario_con_producto_duplicado_falla', function () {
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
});


// --- TESTS DE ACTUALIZACIÓN (SOLO ADMINISTRADOR) ---

test('puede_actualizar_la_cantidad_solo_el_administrador (update)', function () {
    Sanctum::actingAs(User::factory()->create()->assignRole('Administrador'));
    $inventario = Inventario::factory()->create(['cantidad_existencias' => 10]);

    $data = ['cantidad_existencias' => 500];

    $response = $this->putJson("/api/inventario/{$inventario->inventario_id}", $data);

    $response->assertStatus(Response::HTTP_OK);
    
    $this->assertDatabaseHas('inventarios', [
        'inventario_id' => $inventario->inventario_id,
        'cantidad_existencias' => 500,
    ]);
});


// --- TESTS DE ELIMINACIÓN (SOLO ADMINISTRADOR) ---

test('puede_eliminar_un_registro_solo_el_administrador (destroy)', function () {
    Sanctum::actingAs(User::factory()->create()->assignRole('Administrador'));
    $inventario = Inventario::factory()->create();

    $response = $this->deleteJson("/api/inventario/{$inventario->inventario_id}");

    $response->assertStatus(Response::HTTP_NO_CONTENT); 

    $this->assertDatabaseMissing('inventarios', ['inventario_id' => $inventario->inventario_id]);
});

test('usuario_regular_no_puede_eliminar_inventario (destroy_forbidden)', function () {
    Sanctum::actingAs(User::factory()->create()->assignRole('Usuario'));
    $inventario = Inventario::factory()->create();

    $response = $this->deleteJson("/api/inventario/{$inventario->inventario_id}");

    $response->assertStatus(Response::HTTP_FORBIDDEN);
    $this->assertDatabaseHas('inventarios', ['inventario_id' => $inventario->inventario_id]);
});