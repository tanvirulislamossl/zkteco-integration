<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Punch corrections are stored only in this app. The ZKTeco push protocol has no
 * command to change attendance records on the terminal, so the device keeps its original.
 */
class AttendanceLogController extends Controller
{
    public function edit(AttendanceLog $log): View
    {
        return view('zkteco.edit-punch', [
            'log' => $log->load('student'),
            'statuses' => AttendanceLog::STATUSES,
        ]);
    }

    public function update(Request $request, AttendanceLog $log): RedirectResponse
    {
        $validated = $request->validate([
            'punched_at' => ['required', 'date'],
            'status' => ['required', 'integer', Rule::in(array_keys(AttendanceLog::STATUSES))],
        ], [], [
            'punched_at' => 'date & time',
            'status' => 'type',
        ]);

        $log->punched_at = $validated['punched_at'];
        $log->status = (int) $validated['status'];

        $log->edited_at = $log->punched_at->equalTo($log->original_punched_at) && $log->status === $log->original_status
            ? null
            : now();

        $log->save();

        return redirect()->route('punches', ['date' => $log->punched_at->format('Y-m-d')])
            ->with('status', 'Punch updated.');
    }

    public function restore(AttendanceLog $log): RedirectResponse
    {
        $log->update([
            'punched_at' => $log->original_punched_at,
            'status' => $log->original_status,
            'edited_at' => null,
        ]);

        return redirect()->route('punches', ['date' => $log->punched_at->format('Y-m-d')])
            ->with('status', 'Punch restored to the device time.');
    }
}
