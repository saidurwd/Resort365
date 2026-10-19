<?php

/*
| Reading a biometric CSV (Step 6.2): punch lists and daily rows, date and time formats, and row-numbered problems.
*/
use Modules\HR\Services\AttendanceCsv;

it('reads a punch list with one date-time column', function (): void {
    $result = (new AttendanceCsv)->parse("Employee No,Date Time\nEMP-0001,2026-03-02 08:57\nEMP-0001,2026-03-02 17:04:30\nEMP-0002,02/03/2026 09:10\n");

    expect($result['shape'])->toBe('punches')->and($result['errors'])->toBe([])->and($result['rows'])->toBe(3)
        ->and(array_column($result['punches'], 'at'))->toBe(['2026-03-02 08:57', '2026-03-02 17:04', '2026-03-02 09:10'])->and($result['punches'][2]['employee'])->toBe('EMP-0002');
});

it('reads a punch list with a date column and a time column, and semicolon or tab separators', function (): void {
    $semicolon = (new AttendanceCsv)->parse("Badge;Date;Time\n17;2026-03-02;8:57 AM\n17;2026-03-02;05:04 PM\n");
    $tab = (new AttendanceCsv)->parse("Staff ID\tPunch\nEMP-9\t2026-03-02 08:00\n");

    expect(array_column($semicolon['punches'], 'at'))->toBe(['2026-03-02 08:57', '2026-03-02 17:04'])->and($tab['punches'][0]['employee'])->toBe('EMP-9');
});

it('reads daily rows with a clock-in and a clock-out, either of which may be empty', function (): void {
    $result = (new AttendanceCsv)->parse("employee,date,clock in,clock out\nEMP-0001,2026-03-02,08:57,17:04\nEMP-0002,2026-03-02,09:30,\n");

    expect($result['shape'])->toBe('daily')->and($result['days'])->toHaveCount(2)->and($result['days'][0])->toBe(['line' => 2, 'employee' => 'EMP-0001', 'date' => '2026-03-02', 'in' => '08:57', 'out' => '17:04'])
        ->and($result['days'][1]['out'])->toBeNull();
});

it('reports every problem with its line number, and a file it cannot use', function (): void {
    $parser = new AttendanceCsv;
    $bad = $parser->parse("employee,date,in,out\n,2026-03-02,08:00,17:00\nEMP-1,2026-13-45,08:00,17:00\nEMP-1,2026-03-02,8am,17:00\nEMP-1,2026-03-02,,\n");

    expect($bad['errors'])->toHaveCount(4)->and($bad['errors'][0])->toContain('Line 2')->and($bad['errors'][1])->toContain('Line 3')->and($bad['errors'][2])->toContain('Line 4')->and($bad['errors'][3])->toContain('Line 5')
        ->and($parser->parse('')['errors'][0])->toContain('empty')->and($parser->parse("foo,bar\n1,2\n")['errors'][0])->toContain('header row')
        ->and($parser->parse("employee,datetime\n")['errors'][0])->toContain('no attendance lines')->and($parser->parse("employee,datetime\nE1,yesterday\n")['errors'][0])->toContain('Line 2');
});
