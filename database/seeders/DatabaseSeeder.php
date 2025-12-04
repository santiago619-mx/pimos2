<?php

namespace Database\Seeders;

use App\Models\Inventario;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use App\Models\Rol;
use App\Models\DetallePedido; 
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB; 
use Illuminate\Support\Facades\Log; // <--- AGREGAR: Para manejo de errores

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
        // Esta sección está correcta, la mantendremos.
        if (DB::connection() instanceof \Illuminate\Database\SQLiteConnection) {
            DB::statement('PRAGMA foreign_keys = OFF;');
        }
        
        // 2. CREACIÓN DE USUARIOS CLAVE Y ASIGNACIÓN DE ROLES
        
        // Funcióm auxiliar para obtener roles de forma segura
        $getRole = function (string $name) {
            $role = Rol::where('name', $name)->first();
            if (!$role) {
                Log::error("El rol '{$name}' no se encontró. Verifique el RolSeeder.");
            }
            return $role;
        };

        // Obtener los objetos Rol de forma segura
        $adminRole = $getRole('Administrador');
        $editorRole = $getRole('Editor');
        $userRole = $getRole('Usuario');
        
        // Usuario Administrador (Acceso Total)
        $adminUser = User::factory()->create([
            'name' => 'Admin de PIMOS',
            'email' => 'admin@pimos.com', 
            'password' => Hash::make('password'),
        ]);
        if ($adminRole) {
            $adminUser->assignRole($adminRole); 
        }

        // Usuario Editor (Gestión de Productos/Pedidos/Inventario)
        $editorUser = User::factory()->create([
            'name' => 'Editor de PIMOS',
            'email' => 'editor@pimos.com',
            'password' => Hash::make('password'),
        ]);
        if ($editorRole) {
            $editorUser->assignRole($editorRole);
        }
        
        // 3. Crear 48 usuarios aleatorios y asignarles el rol 'Usuario'
        if ($userRole) {
             User::factory(48)->create()->each(function ($user) use ($userRole) {
                $user->assignRole($userRole);
            });
        }
        
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
            ->has(DetallePedido::factory()->count(rand(1, 8)), 'detallesPedidos') 
            ->create();

        // ***** HABILITAR LA VERIFICACIÓN AL FINAL *****
        if (DB::connection() instanceof \Illuminate\Database\SQLiteConnection) {
            DB::statement('PRAGMA foreign_keys = ON;');
        }
    }
}