@extends('layouts.app')

@section('title', 'Punch log')

@push('head')
    <meta http-equiv="refresh" content="5">
@endpush

@section('content')
    <header>
        <div>
            <h1>Punch log</h1>
            <p class="muted">
                Student punches from the device. Refreshes every 5 seconds.
                @forelse ($devices as $device)
                    <span class="badge {{ $device->isOnline() ? 'ok' : '' }}">{{ $device->sn }} · {{ $device->isOnline() ? 'online' : 'offline' }}</span>
                @empty
                    <span class="badge warn">No device connected yet</span>
                @endforelse
            </p>
        </div>

        <form method="GET" action="{{ route('punches') }}" class="form-row">
            <label>
                Date
                <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()">
            </label>
            @if ($date)
                <a class="button" href="{{ route('punches') }}">All dates</a>
            @endif
        </form>
    </header>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Time</th>
                <th>Student name</th>
                <th>Roll</th>
                <th>SID</th>
                <th>Type</th>
                <th>Verified by</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td>
                        {{ $log->punched_at->format('Y-m-d h:i:s A') }}
                        @if ($log->isEdited())
                            <span class="badge warn" title="Device: {{ $log->original_punched_at->format('Y-m-d h:i:s A') }} ({{ $log->originalStatusLabel() }})">Edited</span>
                        @endif
                    </td>
                    <td>
                        @if ($log->student)
                            {{ $log->student->name }}
                        @else
                            <span class="badge warn">Not registered</span>
                        @endif
                    </td>
                    <td>{{ $log->student?->roll ?? '—' }}</td>
                    <td><code>{{ $log->pin }}</code></td>
                    <td>{{ $log->statusLabel() }}</td>
                    <td>{{ $log->verifyLabel() }}</td>
                    <td><a class="button" href="{{ route('punches.edit', $log) }}">Edit</a></td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="empty">
                        No punches {{ $date ? 'on '.$date : 'yet' }}. When a student punches on the device, it shows up here.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
