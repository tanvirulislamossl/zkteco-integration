@extends('layouts.app')

@section('title', 'Students')

@section('content')
    <header>
        <div>
            <h1>Students</h1>
            <p class="muted">The SID must be the same as the student's User ID on the ZKTeco device. That is how punches are matched to a student.</p>
        </div>

        @if ($students->isNotEmpty())
            <form method="POST" action="{{ route('students.sync-all') }}">
                @csrf
                <button type="submit">Sync all names to device</button>
            </form>
        @endif
    </header>

    <div class="card">
        <h2>{{ $editing ? 'Edit student' : 'Add student' }}</h2>
        <form method="POST" action="{{ $editing ? route('students.update', $editing) : route('students.store') }}" class="form-row">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif
            <label>
                Student name
                <input type="text" name="name" value="{{ old('name', $editing?->name) }}" required maxlength="255">
            </label>
            <label>
                Roll
                <input type="text" name="roll" value="{{ old('roll', $editing?->roll) }}" maxlength="50" size="8">
            </label>
            <label>
                SID (device User ID)
                <input type="text" name="sid" value="{{ old('sid', $editing?->sid) }}" required maxlength="20" size="12">
            </label>
            <label class="check">
                <input type="checkbox" name="sync" value="1" @checked(old('sync'))>
                Also sync name to device
            </label>
            <button type="submit" class="primary">{{ $editing ? 'Save' : 'Add' }}</button>
            @if ($editing)
                <a class="button" href="{{ route('students.index') }}">Cancel</a>
            @endif
        </form>
    </div>

    <div class="card">
        <h2>Import students</h2>
        <p class="muted" style="margin-bottom: 12px;">
            Upload a CSV file with columns <code>name</code>, <code>roll</code>, <code>sid</code>.
            In Excel use File → Save As → "CSV UTF-8". Students are matched by SID.
            <a href="{{ route('students.import-template') }}">Download sample file</a>
        </p>

        @if (session('import_errors'))
            <div class="flash bad">
                <strong>Some rows were not imported:</strong>
                <ul>
                    @foreach (session('import_errors') as $importError)
                        <li>{{ $importError }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('students.import') }}" enctype="multipart/form-data" class="form-row">
            @csrf
            <label>
                CSV file
                <input type="file" name="file" accept=".csv,text/csv" required>
            </label>
            <label class="check">
                <input type="checkbox" name="update_existing" value="1" checked>
                Update students whose SID already exists
            </label>
            <label class="check">
                <input type="checkbox" name="sync" value="1">
                Sync imported names to device
            </label>
            <button type="submit" class="primary">Import</button>
        </form>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Student name</th>
                <th>Roll</th>
                <th>SID</th>
                <th>Device name sync</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($students as $student)
                @php
                    $command = $student->latestDeviceCommand;
                @endphp
                <tr>
                    <td>{{ $student->name }}</td>
                    <td>{{ $student->roll ?? '—' }}</td>
                    <td><code>{{ $student->sid }}</code></td>
                    <td>
                        @if (! $command)
                            <span class="badge">Not synced</span>
                        @elseif ($command->status === 'done')
                            <span class="badge ok">Synced {{ $command->completed_at?->diffForHumans() }}</span>
                        @elseif ($command->status === 'failed')
                            <span class="badge bad">Failed (code {{ $command->return_code }})</span>
                        @elseif ($command->status === 'sent')
                            <span class="badge warn">Sent, waiting for device</span>
                        @else
                            <span class="badge warn">Queued</span>
                        @endif
                    </td>
                    <td>
                        <div class="actions">
                            <form method="POST" action="{{ route('students.sync', $student) }}">
                                @csrf
                                <button type="submit">Sync to device</button>
                            </form>
                            <a class="button" href="{{ route('students.edit', $student) }}">Edit</a>
                            <form method="POST" action="{{ route('students.destroy', $student) }}" onsubmit="return confirm(this.dataset.confirm)" data-confirm="Remove {{ $student->name }}?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="danger">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="empty">No students yet. Add one above.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if ($devices->isEmpty())
        <p class="muted" style="margin-top: 14px;">No device has connected yet. Name sync needs the device online, with its ADMS server pointed at this computer.</p>
    @endif
@endsection
