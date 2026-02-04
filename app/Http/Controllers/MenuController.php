<?php

namespace App\Http\Controllers;

use App\Models\Adicional;
use App\Models\CategoriaMenu;
use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MenuController extends Controller
{
    public function store(Request $request)
    {
        // Guardar la imagen en el almacenamiento
        if ($request->hasFile('foto')) {
            $fotoPath = $request->file('foto')->store('menus', 'custom_public');
            $fotoUrl = Storage::url($fotoPath);
        }

        // Crear el menú con la ruta de la imagen
        $menu = Menu::create([
            'titulo' => $request->titulo,
            'descripcion' => $request->descripcion,
            'foto' => $fotoUrl ?? null,
            'precio' => $request->precio,
            'status' => $request->status,
            'empresa_id' => $request->empresa_id,
        ]);

        // Asociar el menú con la categoría
        CategoriaMenu::create([
            'categoria_id' => $request->categoria_id,
            'menu_id' => $menu->id,
        ]);

        return response()->json($menu, 201);
    }

    public function index(Request $request, $empresa_id)
    {
        $categoriaFiltro = $request->query('categoria');

        if ($categoriaFiltro !== null) {
            $menuIds = CategoriaMenu::where('categoria_id', $categoriaFiltro)->pluck('menu_id')->toArray();
            $menus = Menu::where('empresa_id', $empresa_id)->whereIn('id', $menuIds)->get();
        } else {
            $menus = Menu::where('empresa_id', $empresa_id)->get();
        }

        $menusWithCategory = $menus->map(function ($menu) {
            $categoriaMenu = CategoriaMenu::where('menu_id', $menu->id)->first();
            $menuArray = $menu->toArray();
            $menuArray['categoria_id'] = $categoriaMenu ? $categoriaMenu->categoria_id : null;
            // Agregar cantidad de adicionales
            $menuArray['adicionales_count'] = Adicional::where('menu_id', $menu->id)->count();
            return $menuArray;
        });

        return response()->json($menusWithCategory);
    }

    public function updateStatus($id, Request $request)
    {
        $dish = Menu::find($id);

        if (!$dish) {
            return response()->json(['message' => 'Platillo no encontrado'], 404);
        }

        $dish->status = $request->status;
        $dish->save();

        return response()->json(['message' => 'Estado actualizado correctamente', 'dish' => $dish], 200);
    }

    /**
     * Obtener menús agrupados por categoría con filtro de horario
     * Se valida el campo JSON "horarios" de la categoría para determinar si mostrarla
     */
    public function getMenuCategoria($empresa_id)
    {
        // Obtener la hora actual para comparaciones
        $now = now();
        $horaActual = $now->format('H:i');

        // Mapeo de días inglés a español para el JSON
        $diasMap = [
            'Monday' => 'Lunes',
            'Tuesday' => 'Martes',
            'Wednesday' => 'Miércoles',
            'Thursday' => 'Jueves',
            'Friday' => 'Viernes',
            'Saturday' => 'Sábado',
            'Sunday' => 'Domingo',
        ];
        $diaActualEnglish = $now->format('l');
        $diaActualEs = $diasMap[$diaActualEnglish] ?? '';

        // Obtener menús activos de la empresa, cargando categorías activas (sin filtro de hora SQL)
        $menus = Menu::where('empresa_id', $empresa_id)
            ->where('status', 'active')
            ->whereHas('categorias', function ($q) {
                $q->where('estado', 1);
            })
            ->with([
                'categorias' => function ($q) {
                    $q->where('estado', 1);
                }
            ])
            ->get();

        $groupedMenus = [];

        foreach ($menus as $menu) {
            foreach ($menu->categorias as $categoria) {

                // --- Lógica de filtrado por horario JSON ---
                $mostrarCategoria = true;

                if (!empty($categoria->horarios)) {
                    $configDia = null;
                    // Buscar configuración para hoy
                    foreach ($categoria->horarios as $diaConfig) {
                        if (($diaConfig['dia'] ?? '') === $diaActualEs) {
                            $configDia = $diaConfig;
                            break;
                        }
                    }

                    if ($configDia) {
                        $activoDia = $configDia['activo'] ?? false;
                        if (!$activoDia) {
                            // Día inactivo -> No mostrar
                            $mostrarCategoria = false;
                        } else {
                            // Día activo, verificar horas
                            $horaInicio = $configDia['hora_inicio'] ?? null;
                            $horaFin = $configDia['hora_fin'] ?? null;

                            if (!is_null($horaInicio) && !is_null($horaFin)) {
                                // Horario específico definido
                                if ($horaFin < $horaInicio) {
                                    // Cruza medianoche
                                    if (!($horaActual >= $horaInicio || $horaActual <= $horaFin)) {
                                        $mostrarCategoria = false;
                                    }
                                } else {
                                    // Horario normal
                                    if (!($horaActual >= $horaInicio && $horaActual <= $horaFin)) {
                                        $mostrarCategoria = false;
                                    }
                                }
                            }
                            // Si horas son null y activo es true, se considera abierto (mostrarCategoria queda true)
                            // Si horas son null y activo es true, se considera abierto (mostrarCategoria queda true)
                        }
                    }
                    // Si no hay config para hoy en el JSON, depende de la regla de negocio. 
                    // Asumiremos que si hay JSON pero no regla para hoy, usamos el estado global (que ya vimos es 1).
                    // O podría ser false por defecto. Mantendremos true por compatibilidad con "estado 1".
                } else {
                    // Sin JSON horarios -> Aplica solo estado=1 (ya filtrado en query)
                    // Verificar filtro antiguo hora_inicio / hora_fin si aun existen y no son null?
                    // Por ahora priorizamos JSON, si no hay JSON, pasa por estar activo.

                    // (Opcional: mantener lógica legacy si las columnas existen y tienen datos, 
                    // pero el requerimiento es mover a JSON. Asumimos legacy es "siempre abierto" si no tiene JSON).
                }

                if (!$mostrarCategoria) {
                    continue; // Saltar esta categoría para este menú
                }
                // -------------------------------------------

                $categoriaIndex = array_search($categoria->nombre, array_column($groupedMenus, 'nombre'));

                if ($categoriaIndex === false) {
                    $groupedMenus[] = [
                        'nombre' => $categoria->nombre,
                        'items' => []
                    ];
                    $categoriaIndex = array_key_last($groupedMenus);
                }

                // Verificar si el item ya existe en esta categoría (por si acaso duplicados raros)
                // Aunque iteramos menu -> categoria, un menú puede tener varias categorías.
                // Aquí estamos agrupando por nombre de categoría.

                $groupedMenus[$categoriaIndex]['items'][] = [
                    'id' => $menu->id,
                    'empresa_id' => $menu->empresa_id,
                    'titulo' => $menu->titulo,
                    'descripcion' => $menu->descripcion,
                    'foto' => $menu->foto,
                    'precio' => $menu->precio,
                    'status' => $menu->status
                ];
            }
        }

        return response()->json($groupedMenus);
    }
    public function destroy($id)
    {
        try {
            $dish = Menu::find($id);

            if (!$dish) {
                return response()->json(['message' => 'Platillo no encontrado'], 404);
            }

            // Eliminar la relación con categorías
            CategoriaMenu::where('menu_id', $id)->delete();

            // Eliminar adicionales asociados
            Adicional::where('menu_id', $id)->delete();

            // Eliminar la imagen si existe
            if ($dish->foto && Storage::exists($dish->foto)) {
                Storage::delete($dish->foto);
            }

            $dish->delete();

            return response()->json(['message' => 'Platillo eliminado correctamente'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar platillo', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $dish = Menu::find($id);

            if (!$dish) {
                return response()->json(['message' => 'Platillo no encontrado'], 404);
            }

            $dish->titulo = $request->titulo;
            $dish->descripcion = $request->descripcion;
            $dish->precio = $request->precio;
            $dish->status = $request->status;

            if ($request->hasFile('foto')) {
                if ($dish->foto && Storage::exists($dish->foto)) {
                    Storage::delete($dish->foto);
                }
                $fotoPath = $request->file('foto')->store('menus', 'custom_public');
                $dish->foto = Storage::url($fotoPath);
            }

            $dish->save();

            if ($request->has('categoria_id')) {
                CategoriaMenu::where('menu_id', $id)->delete();
                CategoriaMenu::create([
                    'categoria_id' => $request->categoria_id,
                    'menu_id' => $dish->id,
                ]);
            }

            return response()->json([
                'message' => 'Platillo actualizado correctamente',
                'dish' => $dish
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar platillo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getMenusByCategory($categoria_id)
    {
        $menuIds = CategoriaMenu::where('categoria_id', $categoria_id)
            ->pluck('menu_id')
            ->toArray();

        $menus = Menu::whereIn('id', $menuIds)->get();

        return response()->json($menus);
    }

    /**
     * Obtener adicionales de una empresa o de un menú específico
     * 
     * Uso:
     * - GET /get/menu/adicionales/{empresa_id} -> Todos los adicionales de la empresa
     * - GET /get/menu/adicionales/{empresa_id}?menu_id={menu_id} -> Adicionales de un producto específico
     */
    public function getAdicionales(Request $request, $id)
    {
        $menuId = $request->query('menu_id');

        if ($menuId) {
            // Buscamos el menú y cargamos sus grupos con sus respectivos items
            // Usamos 'with' para traer toda la jerarquía en una sola consulta
            $menuConGrupos = Menu::with([
                'grupos' => function ($query) {
                    $query->where('estado', 'active')
                        ->with([
                            'items' => function ($q) {
                                $q->where('status', 'active');
                            }
                        ]);
                }
            ])->find($menuId);

            if (!$menuConGrupos) {
                return response()->json([]);
            }

            // Iterate over groups and items to fix values as requested
            foreach ($menuConGrupos->grupos as $grupo) {
                foreach ($grupo->items as $item) {
                    // 1. Fix: menu_id in items shouldn't be null
                    $item->menu_id = $menuId;

                    // 2. Fix: item price should be the pivot price
                    if ($item->pivot && isset($item->pivot->precio)) {
                        $item->precio = $item->pivot->precio;
                    }
                }
            }

            // Retornamos los grupos, cada uno llevará sus reglas (min/max) e items
            return response()->json($menuConGrupos->grupos);
        }
    }

    /**
     * Obtener adicionales de un menú específico
     */
    public function getMenuAdicionales($menu_id)
    {
        $adicionales = Adicional::where('menu_id', $menu_id)
            ->where('status', 'active')
            ->get();

        return response()->json($adicionales);
    }

    /**
     * Crear adicional para un menú
     */
    public function createMenuAdicional(Request $request, $menu_id)
    {
        $menu = Menu::find($menu_id);

        if (!$menu) {
            return response()->json(['message' => 'Menú no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'precio' => 'required|numeric',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $fotoUrl = '';
        if ($request->hasFile('foto')) {
            $fotoPath = $request->file('foto')->store('adicionales', 'custom_public');
            $fotoUrl = Storage::url($fotoPath);
        }

        $adicional = Adicional::create([
            'menu_id' => $menu_id,
            'empresa_id' => $menu->empresa_id,
            'titulo' => $request->titulo,
            'descripcion' => $request->descripcion ?? '',
            'foto' => $fotoUrl,
            'precio' => $request->precio,
            'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Adicional creado correctamente',
            'adicional' => $adicional
        ], 201);
    }

    /**
     * Actualizar adicional de un menú
     */
    public function updateMenuAdicional(Request $request, $menu_id, $adicional_id)
    {
        $adicional = Adicional::where('id', $adicional_id)
            ->where('menu_id', $menu_id)
            ->first();

        if (!$adicional) {
            return response()->json(['message' => 'Adicional no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'precio' => 'required|numeric',
            'status' => 'sometimes|in:active,inactive',
            'foto' => 'sometimes|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('foto')) {
            if ($adicional->foto) {
                Storage::disk('custom_public')->delete(str_replace('/storage/', '', $adicional->foto));
            }
            $fotoPath = $request->file('foto')->store('adicionales', 'custom_public');
            $adicional->foto = Storage::url($fotoPath);
        }

        $adicional->titulo = $request->titulo;
        $adicional->descripcion = $request->descripcion ?? '';
        $adicional->precio = $request->precio;
        if ($request->has('status')) {
            $adicional->status = $request->status;
        }
        $adicional->save();

        return response()->json([
            'message' => 'Adicional actualizado correctamente',
            'adicional' => $adicional
        ]);
    }

    /**
     * Eliminar adicional de un menú
     */
    public function deleteMenuAdicional($menu_id, $adicional_id)
    {
        $adicional = Adicional::where('id', $adicional_id)
            ->where('menu_id', $menu_id)
            ->first();

        if (!$adicional) {
            return response()->json(['message' => 'Adicional no encontrado'], 404);
        }

        if ($adicional->foto) {
            Storage::disk('custom_public')->delete(str_replace('/storage/', '', $adicional->foto));
        }

        $adicional->delete();

        return response()->json([
            'message' => 'Adicional eliminado correctamente'
        ]);
    }
}
