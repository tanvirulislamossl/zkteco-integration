<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StudentImporter
{
    /**
     * Header spellings accepted for each column (compared lower-case, without spaces/dots/underscores).
     */
    private const HEADERS = [
        'name' => ['name', 'studentname', 'fullname'],
        'roll' => ['roll', 'rollno', 'rollnumber', 'classroll'],
        'sid' => ['sid', 'studentid', 'id', 'userid', 'pin', 'identificationnumber', 'studentidentificationnumber'],
    ];

    private const MAX_ERRORS = 20;

    /**
     * Import students from a CSV file. Rows are matched to existing students by SID.
     *
     * @return array{created: list<Student>, updated: list<Student>, skipped: int, errors: list<string>}
     */
    public function import(string $path, bool $updateExisting): array
    {
        $result = ['created' => [], 'updated' => [], 'skipped' => 0, 'errors' => []];
        $rows = $this->readRows($path);

        if ($rows === []) {
            $result['errors'][] = 'The file is empty.';

            return $result;
        }

        $columns = $this->detectColumns($rows[0]);
        $firstDataRow = 1;

        if ($columns === null) {
            // No header row: assume the column order of the template.
            $columns = ['name' => 0, 'roll' => 1, 'sid' => 2];
            $firstDataRow = 0;
        }

        $seenSids = [];

        DB::transaction(function () use ($rows, $columns, $firstDataRow, $updateExisting, &$seenSids, &$result) {
            foreach (array_slice($rows, $firstDataRow, preserve_keys: true) as $index => $row) {
                $line = $index + 1;

                if (count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }

                $data = [
                    'name' => trim((string) ($row[$columns['name']] ?? '')),
                    'roll' => $columns['roll'] === null ? null : (trim((string) ($row[$columns['roll']] ?? '')) ?: null),
                    'sid' => trim((string) ($row[$columns['sid']] ?? '')),
                ];

                $validator = Validator::make($data, [
                    'name' => ['required', 'string', 'max:255'],
                    'roll' => ['nullable', 'string', 'max:50'],
                    'sid' => ['required', 'regex:/^[A-Za-z0-9]+$/', 'max:20'],
                ], [
                    'sid.regex' => 'SID may only contain letters and numbers.',
                ], [
                    'sid' => 'SID',
                ]);

                if ($validator->fails()) {
                    $this->addError($result, "Row {$line}: ".$validator->errors()->first());

                    continue;
                }

                if (isset($seenSids[$data['sid']])) {
                    $this->addError($result, "Row {$line}: SID {$data['sid']} already appears on row {$seenSids[$data['sid']]}.");

                    continue;
                }

                $seenSids[$data['sid']] = $line;

                $student = Student::query()->where('sid', $data['sid'])->first();

                if ($student === null) {
                    $result['created'][] = Student::query()->create($data);
                } elseif ($updateExisting) {
                    $student->update($data);
                    $result['updated'][] = $student;
                } else {
                    $result['skipped']++;
                }
            }
        });

        return $result;
    }

    /**
     * Example file offered for download.
     */
    public function template(): string
    {
        return "name,roll,sid\nRahim Uddin,1,1001\nKarim Hasan,2,1002\n";
    }

    /**
     * @return list<list<string|null>>
     */
    private function readRows(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        if (! mb_check_encoding($content, 'UTF-8')) {
            // Plain "CSV" from Excel is saved in the Windows code page.
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $firstLine = strtok($content, "\r\n") ?: '';
        $delimiter = collect([',', ';', "\t"])
            ->sortByDesc(fn (string $candidate) => substr_count($firstLine, $candidate))
            ->first();

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $rows = [];

        while (($row = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            $rows[] = $row;
        }

        fclose($stream);

        return $rows;
    }

    /**
     * @param  list<string|null>  $header
     * @return array{name: int, roll: int|null, sid: int}|null
     */
    private function detectColumns(array $header): ?array
    {
        $columns = ['name' => null, 'roll' => null, 'sid' => null];

        foreach ($header as $position => $label) {
            $key = strtolower((string) preg_replace('/[\s._\-]+/', '', (string) $label));

            foreach (self::HEADERS as $column => $aliases) {
                if ($columns[$column] === null && in_array($key, $aliases, true)) {
                    $columns[$column] = $position;
                }
            }
        }

        if ($columns['name'] === null || $columns['sid'] === null) {
            return null;
        }

        return $columns;
    }

    /**
     * @param  array{errors: list<string>}  $result
     */
    private function addError(array &$result, string $message): void
    {
        if (count($result['errors']) < self::MAX_ERRORS) {
            $result['errors'][] = $message;
        } elseif (count($result['errors']) === self::MAX_ERRORS) {
            $result['errors'][] = 'More rows have errors; fix the ones above and import again.';
        }
    }
}
