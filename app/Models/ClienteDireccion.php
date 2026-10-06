<?php

namespace App\Models;

use App\Services\CoordenadasService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ClienteDireccion extends Model
{
    use HasFactory;
    protected $table = 'clientes_direcciones';

    protected $fillable = [
        'id_cliente',
        'direccion',
        'departamento',
        'referencia',
        'alias',
        'coordenadas',
        'activa',
        'version',
    ];

    protected $casts = [
        'activa' => 'boolean',
    ];

    /**
     * La dirección "vigente" del cliente: la activa y, si no hay ninguna marcada, la más
     * antigua. Para quien tiene una sola dirección es la misma de siempre, así que todo el
     * código (y las apps antiguas) que leían "la dirección del cliente" sigue igual.
     */
    public static function vigente(int|string|null $idCliente): ?self
    {
        if (!$idCliente) {
            return null;
        }

        return static::where('id_cliente', $idCliente)
            ->orderByDesc('activa')
            ->orderBy('id')
            ->first();
    }

    /** Marca esta dirección como la activa del cliente y desmarca las demás. */
    public function activar(): void
    {
        static::where('id_cliente', $this->id_cliente)->where('id', '!=', $this->id)->update(['activa' => false]);
        if (!$this->activa) {
            $this->update(['activa' => true]);
        }
    }

    /**
     * Cambia el lugar de la dirección. Si el lugar cambió de verdad (otro texto u otro punto a
     * más de 50 m), sube la versión —así las notas de la casa de antes dejan de aplicar— y se
     * borra la referencia anterior, que describía otro sitio (salvo que llegue una nueva).
     *
     * @param array{direccion: string, coordenadas: mixed, departamento?: ?string, referencia?: ?string, alias?: ?string} $datos
     */
    public function actualizarUbicacion(array $datos): void
    {
        $normalizar = fn (?string $t) => trim(preg_replace('/\s+/', ' ', mb_strtolower((string) $t)));
        $coords = app(CoordenadasService::class);

        $antes = $coords->desdeDireccion($this->coordenadas);
        $despues = $coords->desdeDireccion($datos['coordenadas']);

        $textoCambio = $normalizar($this->direccion) !== $normalizar($datos['direccion']);
        $puntoCambio = $antes && $despues && $this->metros($antes, $despues) > 50;
        $cambioReal = $this->exists && ($textoCambio || $puntoCambio);

        $cambios = [
            'direccion' => $datos['direccion'],
            'coordenadas' => is_string($datos['coordenadas']) ? $datos['coordenadas'] : json_encode($datos['coordenadas']),
        ];
        if (array_key_exists('departamento', $datos) && $datos['departamento'] !== null) {
            $cambios['departamento'] = $datos['departamento'];
        }
        if (array_key_exists('alias', $datos) && $datos['alias'] !== null) {
            $cambios['alias'] = $datos['alias'];
        }
        if (!empty($datos['referencia'])) {
            $cambios['referencia'] = $datos['referencia'];
        } elseif ($cambioReal) {
            $cambios['referencia'] = null;
        }
        if ($cambioReal) {
            $cambios['version'] = ((int) $this->version ?: 1) + 1;
        }

        $this->update($cambios);
    }

    private function metros(array $a, array $b): float
    {
        $radio = 6371000;
        $dLat = deg2rad($b['lat'] - $a['lat']);
        $dLng = deg2rad($b['lng'] - $a['lng']);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($a['lat'])) * cos(deg2rad($b['lat'])) * sin($dLng / 2) ** 2;

        return 2 * $radio * asin(sqrt($h));
    }
}
