<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

class Negocio extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'tipo_negocio_id',
        'categoria_id',
        'user_id',
        'total_sucursales',
        'es_local_calle',
        'metodo_contacto',
        'telefono',
        'activo',
        'business_registration_id',
        'tipo_pago_digital', // 0 ninguno ,1 yapé y 2 plin
        'numero_pago_digital', // numero de pago digital segun el tipo
        'nombre_titular_pago_digital', // nombre de la persona del pago digital
        'qr_pago_digital', // ruta de la imagen del QR de Yape/Plin (opcional)
    ];

    public function tipoNegocio()
    {
        return $this->belongsTo(TipoNegocio::class);
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sucursales()
    {
        return $this->hasMany(Sucursal::class);
    }

    public function businessRegistration()
    {
        return $this->belongsTo(BusinessRegistration::class);
    }

    /**
     * Guarda la imagen del QR de Yape/Plin en public/qr-pago-digital, reemplazando la anterior.
     * Usado desde el registro del socio y desde su panel.
     */
    public function reemplazarQrPagoDigital(UploadedFile $file): string
    {
        $path = public_path('qr-pago-digital');
        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0777, true, true);
        }

        $this->borrarArchivoQrPagoDigital();

        $fileName = time() . '_' . $file->getClientOriginalName();
        $file->move($path, $fileName);
        $rutaRelativa = 'qr-pago-digital/' . $fileName;

        $this->update(['qr_pago_digital' => $rutaRelativa]);

        return $rutaRelativa;
    }

    public function eliminarQrPagoDigital(): void
    {
        if ($this->qr_pago_digital) {
            $this->borrarArchivoQrPagoDigital();
            $this->update(['qr_pago_digital' => null]);
        }
    }

    private function borrarArchivoQrPagoDigital(): void
    {
        if ($this->qr_pago_digital) {
            $rutaAnterior = public_path($this->qr_pago_digital);
            if (File::exists($rutaAnterior)) {
                File::delete($rutaAnterior);
            }
        }
    }
}