<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Validation\Rule;
use Tests\TestCase;

use App\Models\Pedido;
use App\Models\Producto;
//use App\Models\DetallePedido;
//use App\Models\Inventario;
use App\Models\User;
use Laravel\Sanctum\Sanctum;



uses(RefreshDatabase::class); 
uses(\Illuminate\Foundation\Testing\WithFaker::class); 

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolSeeder']);
});

// --- TESTS DE LECTURA (viewAny restringido a Admin, view permitido a dueño/Admin) ---

test('puede_listar_todos_los_pedidos_solo_el_administrador (index)', function () {
    // Actuar como Administrador
    Sanctum::actingAs(User::factory()->create()->assignRole('Administrador'));
    
    Pedido::factory(5)->create();

    $response = $this->getJson('/api/pedidos');

    $response->assertStatus(Response::HTTP_OK)
             ->assertJsonCount(5, 'data');
});

test('usuario_regular_no_puede_acceder_al_listado_global_de_pedidos (index_forbidden)', function () {
    // Actuar como Usuario simple
    Sanctum::actingAs(User::factory()->create()->assignRole('Usuario'));

    $response = $this->getJson('/api/pedidos');

    $response->assertStatus(Response::HTTP_FORBIDDEN);
});


test('puede_ver_su_propio_pedido (show_owner)', function () {
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
});

test('usuario_no_puede_ver_pedido_de_otro_usuario (show_other_forbidden)', function () {
    $owner = User::factory()->create();
    $otroUsuario = User::factory()->create()->assignRole('Usuario');
    Sanctum::actingAs($otroUsuario); // Actúa como el otro usuario
    
    // Pedido creado por el dueño
    $pedido = Pedido::factory()->create(['user_id' => $owner->id]);

    $response = $this->getJson("/api/pedidos/{$pedido->id}");

    $response->assertStatus(Response::HTTP_FORBIDDEN);
});


// --- TESTS DE CREACIÓN (Cualquier usuario autenticado) ---

test('usuario_puede_crear_un_pedido_valido (store)', function () {
    $usuario = Sanctum::actingAs(User::factory()->create()->assignRole('Usuario'));
    
    // Crear un producto con stock
    $producto = Producto::factory()->hasInventario(1, ['cantidad_existencias' => 50])->create(['precio' => 10.00]);

    $data = [
        'user_id' => $usuario->id, // Debe coincidir con el usuario autenticado (aunque la request no lo verifica, es buena práctica)
        'detalles' => [
            [
                'producto_id' => $producto->id,
                'cantidad' => 5, // Suficiente stock
                'precio_unitario' => 10.00, // Se ignora en el controlador, pero se envía
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
});

test('crear_un_pedido_falla_si_no_hay_suficiente_stock', function () {
    $usuario = Sanctum::actingAs(User::factory()->create()->assignRole('Usuario'));
    
    // Producto con poco stock
    $producto = Producto::factory()->hasInventario(1, ['cantidad_existencias' => 5])->create();

    $data = [
        'user_id' => $usuario->id,
        'detalles' => [
            [
                'producto_id' => $producto->id,
                'cantidad' => 10, // Pide más de 5
            ]
        ]
    ];

    $response = $this->postJson('/api/pedidos', $data);

    // El controlador debe retornar 422 (Unprocessable Entity) o 400 (Bad Request) por la falta de stock.
    // Asumiremos 422 con un error de validación personalizado.
    $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
             ->assertJson(['message' => 'Stock insuficiente para el producto.']); // Mensaje esperado del controlador
});


// --- TESTS DE ACTUALIZACIÓN (Dueño/Admin) ---

test('puede_actualizar_su_propio_pedido_el_dueño_del_pedido (update_owner)', function () {
    $owner = User::factory()->create()->assignRole('Usuario');
    Sanctum::actingAs($owner);
    
    $pedido = Pedido::factory()->create(['user_id' => $owner->id, 'estado' => 'pendiente']);

    // El dueño solo intenta cambiar el estado (ej: cancelar)
    $data = ['estado' => 'cancelado'];

    $response = $this->putJson("/api/pedidos/{$pedido->id}", $data);

    $response->assertStatus(Response::HTTP_OK)
             ->assertJsonFragment(['estado' => 'cancelado']);

    $this->assertDatabaseHas('pedidos', ['id' => $pedido->id, 'estado' => 'cancelado']);
});

test('usuario_no_puede_actualizar_pedido_de_otro_usuario (update_other_forbidden)', function () {
    $owner = User::factory()->create();
    $otroUsuario = User::factory()->create()->assignRole('Usuario');
    Sanctum::actingAs($otroUsuario);
    
    $pedido = Pedido::factory()->create(['user_id' => $owner->id]);

    $response = $this->putJson("/api/pedidos/{$pedido->id}", ['estado' => 'cancelado']);

    $response->assertStatus(Response::HTTP_FORBIDDEN);
    $this->assertDatabaseHas('pedidos', ['id' => $pedido->id, 'estado' => $pedido->estado]); // Sin cambios
});


// --- TESTS DE ELIMINACIÓN (Dueño/Admin) ---

test('puede_eliminar_un_pedido_solo_el_administrador (destroy)', function () {
    Sanctum::actingAs(User::factory()->create()->assignRole('Administrador'));
    
    // Crear un pedido
    $pedido = Pedido::factory()->create();

    $response = $this->deleteJson("/api/pedidos/{$pedido->id}");

    $response->assertStatus(Response::HTTP_NO_CONTENT); 
    $this->assertDatabaseMissing('pedidos', ['id' => $pedido->id]);
});

test('puede_eliminar_su_propio_pedido_el_dueño_del_pedido (destroy_owner)', function () {
    $owner = User::factory()->create()->assignRole('Usuario');
    Sanctum::actingAs($owner);
    
    $pedido = Pedido::factory()->create(['user_id' => $owner->id]);

    $response = $this->deleteJson("/api/pedidos/{$pedido->id}");

    $response->assertStatus(Response::HTTP_NO_CONTENT); 
    $this->assertDatabaseMissing('pedidos', ['id' => $pedido->id]);
});