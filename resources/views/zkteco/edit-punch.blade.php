@extends('layouts.app')

@section('title', 'Edit punch')

@section('content')
    <header>
        <div>
            <h1>Edit punch</h1>
            <p class="muted">
                {{ $log->student?->name ?? 'Not registered' }}
                @if ($log->student?->roll)
                    · Roll {{ $log->student->roll }}
                @endif
                · SID <code>{{ $log->pin }}</code>
            </p>
        </div>
    </header>

    <div class="card">
        <p class="muted" style="margin-bottom: 14px;">
            Device time: <strong>{{ $log->original_punched_at->format('Y-m-d h:i:s A') }}</strong> ({{ $log->originalStatusLabel() }}).
            The change is saved in this app only. ZKTeco devices do not allow attendance records to be changed, so the device keeps its original record.
        </p>

        <form method="POST" action="{{ route('punches.update', $log) }}" class="form-row">
            @csrf
            @method('PUT')
            <label>
                Date &amp; time
                <input type="datetime-local" name="punched_at" step="1" required
                       value="{{ old('punched_at', $log->punched_at->format('Y-m-d\TH:i:s')) }}">
            </label>
            <label>
                Type
                <select name="status">
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected((int) old('status', $log->status ?? 0) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="primary">Save</button>
            <a class="button" href="{{ route('punches', ['date' => $log->punched_at->format('Y-m-d')]) }}">Cancel</a>
        </form>

        @if ($log->isEdited())
            <form method="POST" action="{{ route('punches.restore', $log) }}" style="margin-top: 14px;">
                @csrf
                <button type="submit">Restore device time</button>
            </form>
        @endif
    </div>
@endsection
