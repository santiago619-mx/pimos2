<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;     // Importar el modelo Role de Spatie
use Spatie\Permission\Models\Permission; // Importar el modelo Permission de Spatie
use App\Models\User; // Importar el modelo User para asignar roles

class RolSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // 1. DEFINICIÓN DE ROLES
        // Creamos los roles principales
        $administrador = Role::create(['name' => 'Administrador']);
        $editor = Role::create(['name' => 'Editor']); // Puede ver/editar productos, ver inventario y procesar pedidos.
        $usuario = Role::create(['name' => 'Usuario']); // Cliente final: puede ver productos y crear sus propios pedidos.

        // 2. DEFINICIÓN DE PERMISOS (Agrupados por Recurso)

        // --- Permisos de Productos (CRUD completo) ---
        $verProductos = Permission::create(['name' => 'productos.ver', 'description' => 'Visualizar lista y detalle de productos']);
        $crearProductos = Permission::create(['name' => 'productos.crear', 'description' => 'Crear nuevos productos']);
        $editarProductos = Permission::create(['name' => 'productos.editar', 'description' => 'Modificar productos existentes']);
        $eliminarProductos = Permission::create(['name' => 'productos.eliminar', 'description' => 'Eliminar productos']);

        // --- Permisos de Inventario (CRUD completo) ---
        // Permiso AÑADIDO
        $crearInventario = Permission::create(['name' => 'inventario.crear', 'description' => 'Crear nuevos registros de inventario (Ej: nuevos almacenes, tipos de stock)']);
        $verInventario = Permission::create(['name' => 'inventario.ver', 'description' => 'Visualizar el inventario y stock actual']);
        $gestionarInventario = Permission::create(['name' => 'inventario.gestionar', 'description' => 'Actualizar y reabastecer stock (Entradas/Salidas manuales)']);
        // Permiso AÑADIDO
        $eliminarInventario = Permission::create(['name' => 'inventario.eliminar', 'description' => 'Eliminar registros de inventario']);

        // --- Permisos de Pedidos ---
        $verPedidos = Permission::create(['name' => 'pedidos.ver', 'description' => 'Visualizar lista completa de pedidos (Admin/Editor)']);
        $crearPedidos = Permission::create(['name' => 'pedidos.crear', 'description' => 'Crear nuevos pedidos']); // Necesario para todos: Admin, Editor y Usuario/Cliente
        $procesarPedidos = Permission::create(['name' => 'pedidos.procesar', 'description' => 'Cambiar estado de pedidos (Ej: Pendiente -> Enviado)']);
        $cancelarPedidos = Permission::create(['name' => 'pedidos.cancelar', 'description' => 'Eliminar o anular pedidos']);
        
        // 3. ASIGNACIÓN DE PERMISOS A ROLES

        // ADMINISTRADOR: Acceso Total (CRUD en todos los módulos)
        $administrador->syncPermissions([
            $verProductos, $crearProductos, $editarProductos, $eliminarProductos,
            // CRUD COMPLETO en Inventario
            $crearInventario, $verInventario, $gestionarInventario, $eliminarInventario, 
            $verPedidos, $crearPedidos, $procesarPedidos, $cancelarPedidos
        ]);

        // EDITOR: Gestión de Productos y Procesamiento de Pedidos
        $editor->syncPermissions([
            $verProductos, $editarProductos, // Puede ver y editar productos, pero no crearlos o eliminarlos.
            $verInventario, $gestionarInventario, // Puede ver el stock Y GESTIONAR las entradas/salidas (edición).
            $verPedidos, $crearPedidos, $procesarPedidos // Puede crear nuevos pedidos y procesar los existentes.
        ]);

        // USUARIO: Cliente final
        $usuario->syncPermissions([
            $verProductos,                  // Puede ver los productos disponibles.
            $crearPedidos                   // Puede crear un pedido.
        ]);


        // 4. ASIGNACIÓN DEL ROL ADMINISTRADOR A UN USUARIO (Recomendado)
        // Esto asume que tienes un usuario con el email 'admin@gomitas.com' creado.
        $adminUser = User::where('email', 'admin@gomitas.com')->first();
        if ($adminUser) {
            $adminUser->assignRole($administrador);
            $this->command->info('Rol Administrador asignado a admin@gomitas.com');
        } else {
             $this->command->warn('Usuario admin@gomitas.com no encontrado. El rol de Administrador no fue asignado.');
        }

    }
}