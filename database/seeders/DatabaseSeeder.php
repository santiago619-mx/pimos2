<?php

namespace Database\Seeders;

use App\Models\Inventario;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use App\Models\DetallePedido; // Modelo necesario para el factory encadenado
use Illuminate\Database\Eloquent\Factories\Factory; 
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB; 

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. LLAMAR AL SEEDER DE ROLES Y PERMISOS
        $this->call(RolSeeder::class);

        // ***** SOLUCIÓN DEFINITIVA PARA SQLITE (Pragma de Foráneas) *****
        if (DB::connection() instanceof \Illuminate\Database\SQLiteConnection) {
            DB::statement('PRAGMA foreign_keys = OFF;');
        }
        
        // 2. CREACIÓN DE USUARIOS CLAVE Y ASIGNACIÓN DE ROLES
        
        // Usuario Administrador (Acceso Total)
        User::factory()->create([
            'name' => 'Admin de PIMOS',
            'email' => 'admin@pimos.com', 
            'password' => Hash::make('password'), // Contraseña: password
        ])->assignRole('Administrador'); // Asignar rol de Administrador

        // Usuario Editor (Gestión de Productos/Pedidos/Inventario)
        User::factory()->create([
            'name' => 'Editor de PIMOS',
            'email' => 'editor@pimos.com',
            'password' => Hash::make('password'), // Contraseña: password
        ])->assignRole('Editor'); // Asignar rol de Editor
        
        // 3. Crear 48 usuarios aleatorios y asignarles el rol 'Usuario'
        User::factory(48)->create()->each(function ($user) {
            $user->assignRole('Usuario');
        });
        
        // 4. Crear Productos de Prueba (50 tipos de gomitas, por ejemplo)
        $productos = Producto::factory(50)->create();

        // 5. Crear Inventario (stock inicial para cada uno de los 50 productos)
        foreach ($productos as $producto) {
            Inventario::factory()->create([
                'producto_id' => $producto->id,
            ]);
        }

        // 6. Crear Pedidos y sus Detalles (200 Pedidos)
        Pedido::factory(200)
            // DetallePedidoFactory existe y usa el nombre de relación 'detallesPedidos'
            ->has(DetallePedido::factory()->count(rand(1, 8)), 'detallesPedidos') 
            ->create();

        // ***** HABILITAR LA VERIFICACIÓN AL FINAL *****
        if (DB::connection() instanceof \Illuminate\Database\SQLiteConnection) {
            DB::statement('PRAGMA foreign_keys = ON;');
        }
    }
}