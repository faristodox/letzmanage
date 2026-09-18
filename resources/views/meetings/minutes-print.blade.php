<!DOCTYPE html>
<html lang="{{ $language === 'ms' ? 'ms' : 'en' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $meeting->title }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 2.5rem 3rem;
            font-family: Georgia, 'Times New Roman', serif;
            color: #1e293b;
            line-height: 1.5;
            max-width: 850px;
            margin-inline: auto;
        }
        .no-print { text-align: right; margin-bottom: 1.5rem; }
        .no-print button {
            font-family: system-ui, sans-serif;
            font-size: 0.875rem;
            font-weight: 600;
            padding: 0.5rem 1.25rem;
            border-radius: 0.5rem;
            border: 1px solid #cbd5e1;
            background: #4f46e5;
            color: #fff;
            cursor: pointer;
        }
        header.doc-header { margin-bottom: 1.5rem; }
        header.doc-header h1 { margin: 0 0 0.5rem; font-size: 1.3rem; }
        header.doc-header p { margin: 0.15rem 0; font-size: 0.9rem; }
        section { margin-bottom: 1.75rem; }
        section h3 {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #4338ca;
            border-bottom: 1px solid #c7d2fe;
            padding-bottom: 0.25rem;
            margin: 0 0 0.65rem;
        }
        table.attendance-table, table.agenda-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        table.attendance-table th, table.attendance-table td,
        table.agenda-table th, table.agenda-table td {
            text-align: left;
            padding: 0.4rem 0.6rem;
            border: 1px solid #cbd5e1;
            vertical-align: top;
        }
        table.attendance-table th, table.agenda-table th { background: #f8fafc; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; }
        table.attendance-table td.no, table.agenda-table td.no { width: 2.5rem; text-align: center; }
        table.agenda-table td.action, table.agenda-table td.notes { width: 8rem; }
        table.agenda-table ol { margin: 0; padding-left: 1.1rem; }
        p.attendance-note { margin: 0.5rem 0 0; font-size: 0.75rem; color: #94a3b8; font-style: italic; }
        .signoff-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; margin-top: 2.5rem; page-break-inside: avoid; }
        .signoff-grid .col p.role { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; margin: 0 0 2rem; }
        .signoff-grid .col .name { font-weight: 700; text-transform: uppercase; margin: 0; }
        .signoff-grid .col .position { font-size: 0.85rem; color: #64748b; margin: 0.15rem 0 0; }
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button type="button" onclick="window.print()">{{ $language === 'ms' ? 'Cetak / Simpan sebagai PDF' : 'Print / Save as PDF' }}</button>
    </div>

    <header class="doc-header">
        <h1>{{ $meeting->title }}</h1>
        @if ($schedule['date'])
            <p>{{ $schedule['date'] }}{{ $schedule['day'] ? " / {$schedule['day']}" : '' }}</p>
        @endif
        @if ($schedule['time'])
            <p>{{ $schedule['time'] }}</p>
        @endif
        @if ($schedule['location'])
            <p>{{ $schedule['location'] }}</p>
        @endif
    </header>

    <section>
        <h3>{{ $language === 'ms' ? 'Kehadiran' : 'Attendance' }}</h3>
        @if (empty($attendees))
            <p>{{ $language === 'ms' ? 'Tiada rekod kehadiran.' : 'No attendance recorded.' }}</p>
        @else
            <table class="attendance-table">
                <thead>
                    <tr>
                        <th class="no">{{ __('No') }}</th>
                        <th>{{ $language === 'ms' ? 'Nama' : 'Name' }}</th>
                        <th>{{ $language === 'ms' ? 'Jawatan' : 'Position' }}</th>
                        <th>{{ $language === 'ms' ? 'Kehadiran' : 'Attendance' }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($attendees as $index => $attendee)
                        <tr>
                            <td class="no">{{ $index + 1 }}</td>
                            <td>{{ $attendee['name'] }}</td>
                            <td>{{ $attendee['position'] ?: '—' }}</td>
                            <td>{{ $language === 'ms' ? 'Hadir' : 'Present' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="attendance-note">
                {{ $attendeesConfirmed
                    ? ($language === 'ms' ? 'Disahkan melalui daftar masuk.' : 'Confirmed via check-in.')
                    : ($language === 'ms' ? 'Dikenal pasti daripada rakaman — belum disahkan melalui daftar masuk.' : 'Identified from the recording — not confirmed via check-in.') }}
            </p>
        @endif
    </section>

    <section>
        <h3>{{ $language === 'ms' ? 'Agenda' : 'Agenda' }}</h3>
        @if (empty($agendaItems))
            <p>{{ $language === 'ms' ? 'Tiada agenda berstruktur tersedia.' : 'No structured agenda available.' }}</p>
        @else
            <table class="agenda-table">
                <thead>
                    <tr>
                        <th class="no">{{ __('No') }}</th>
                        <th>{{ $language === 'ms' ? 'Agenda' : 'Agenda' }}</th>
                        <th class="action">{{ $language === 'ms' ? 'Tindakan' : 'Action' }}</th>
                        <th class="notes">{{ $language === 'ms' ? 'Catatan' : 'Notes' }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($agendaItems as $index => $item)
                        <tr>
                            <td class="no">{{ $index + 1 }}</td>
                            <td>
                                <strong>{{ $item['topic'] ?? '' }}</strong>
                                @if (! empty($item['sub_points']))
                                    <ol>
                                        @foreach ($item['sub_points'] as $subPoint)
                                            <li>{{ $subPoint }}</li>
                                        @endforeach
                                    </ol>
                                @endif
                            </td>
                            <td class="action">{{ $item['action_by'] ?? '' }}</td>
                            <td class="notes">{{ $item['notes'] ?? '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <div class="signoff-grid">
        <div class="col">
            <p class="role">{{ $language === 'ms' ? 'Disediakan oleh,' : 'Prepared by,' }}</p>
            <p class="name">{{ $preparedBy['name'] ?: '—' }}</p>
            <p class="position">{{ $preparedBy['position'] }}</p>
        </div>
        <div class="col">
            <p class="role">{{ $language === 'ms' ? 'Disahkan oleh,' : 'Confirmed by,' }}</p>
            <p class="name">{{ $confirmedBy['name'] ?: '—' }}</p>
            <p class="position">{{ $confirmedBy['position'] }}</p>
        </div>
    </div>
</body>
</html>
