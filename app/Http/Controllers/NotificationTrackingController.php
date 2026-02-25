<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\NotificationLog;
use Illuminate\Support\Facades\Log;

class NotificationTrackingController extends Controller
{
    public function updateStatus(Request $request)
    {
        $request->validate([
            'notification_id' => 'required|exists:notification_logs,id',
            'status' => 'required|in:received,opened',
        ]);

        try {
            $log = NotificationLog::findOrFail($request->notification_id);
            
            if ($request->status === 'received') {
                $log->update(['received_at' => now()]);
            } elseif ($request->status === 'opened') {
                $log->update(['opened_at' => now()]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Notification status updated'
            ]);
        } catch (\Exception $e) {
            Log::error("Error actualizando trazabilidad de notificación: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update notification status'
            ], 500);
        }
    }
}
