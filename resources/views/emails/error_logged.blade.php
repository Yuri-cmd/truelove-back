<x-mail::message>
# Se ha registrado un nuevo error

Se ha detectado un error en la aplicación **{{ $errorLog->app_name }}**.

**Mensaje:**
{{ $errorLog->error_message }}

**URL:** {{ $errorLog->url ?? 'N/A' }}
**Método:** {{ $errorLog->method ?? 'N/A' }}
**Usuario ID:** {{ $errorLog->user_id ?? 'N/A' }}

**Información del Dispositivo:**
@if($errorLog->device_info)
<pre>{{ json_encode($errorLog->device_info, JSON_PRETTY_PRINT) }}</pre>
@else
N/A
@endif

**Stack Trace:**
<pre style="font-size: 10px; overflow-x: auto;">
{{ $errorLog->stack_trace }}
</pre>

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
