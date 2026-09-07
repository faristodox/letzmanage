<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ __('Program Report') }} - {{ $event->title }}</title>
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
        header.doc-header { text-align: center; margin-bottom: 2rem; }
        header.doc-header img.banner { display: block; width: 100%; max-height: 220px; object-fit: cover; border-radius: 0.5rem; margin-bottom: 1.25rem; }
        header.doc-header p.org-name { margin: 0; font-size: 0.95rem; letter-spacing: 0.05em; text-transform: uppercase; color: #475569; }
        header.doc-header h1 { margin: 0.35rem 0; font-size: 1.5rem; letter-spacing: 0.02em; }
        header.doc-header h2 { margin: 0; font-size: 1.15rem; font-weight: normal; color: #334155; }
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
        section p { margin: 0; white-space: pre-line; }
        table.details-table, table.itinerary-table, table.finance-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        table.details-table td { padding: 0.3rem 0; vertical-align: top; }
        table.details-table td.label { width: 9rem; font-weight: 600; color: #475569; }
        table.itinerary-table th, table.itinerary-table td, table.finance-table th, table.finance-table td {
            text-align: left;
            padding: 0.4rem 0.6rem;
            border: 1px solid #e2e8f0;
        }
        table.itinerary-table th, table.finance-table th { background: #f8fafc; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; }
        table.finance-table td.amount, table.finance-table th.amount { text-align: right; }
        .stat-row { display: flex; gap: 2rem; flex-wrap: wrap; font-size: 0.9rem; }
        .stat-row div { min-width: 8rem; }
        .stat-row .stat-value { font-size: 1.25rem; font-weight: 700; }
        .signoff-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-top: 2.5rem; page-break-inside: avoid; }
        .signoff-grid .col p.role { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; margin: 0 0 0.35rem; }
        .signoff-grid .col .signature-space { height: 2.5rem; display: flex; align-items: flex-end; }
        .signoff-grid .col .signature-space img { max-height: 2.5rem; max-width: 100%; object-fit: contain; }
        .signoff-grid .col .line { border-top: 1px solid #1e293b; padding-top: 0.35rem; }
        .signoff-grid .col .name { font-weight: 600; }
        .signoff-grid .col .position, .signoff-grid .col .date { font-size: 0.85rem; color: #64748b; }
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button type="button" onclick="window.print()">{{ __('Print / Save as PDF') }}</button>
    </div>

    <header class="doc-header">
        @if ($event->bannerUrl())
            <img src="{{ $event->bannerUrl() }}" alt="{{ $event->title }}" class="banner">
        @endif
        @if ($event->organization)
            <p class="org-name">{{ $event->organization->name }}</p>
        @endif
        <h1>{{ __('Program Report') }}</h1>
        <h2>{{ $event->title }}</h2>
    </header>

    <section>
        <h3>{{ __('Program Details') }}</h3>
        <table class="details-table">
            <tr>
                <td class="label">{{ __('Date') }}</td>
                <td>{{ $reportDetail?->event_date?->format('d M Y') ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('Time') }}</td>
                <td>{{ $reportDetail?->event_time ?: '—' }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('Theme') }}</td>
                <td>{{ $reportDetail?->theme ?: '—' }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('Venue') }}</td>
                <td>{{ $reportDetail?->venue ?: '—' }}</td>
            </tr>
        </table>
    </section>

    @if ($reportDetail?->objectives)
        <section>
            <h3>{{ __('Objectives') }}</h3>
            <p>{{ $reportDetail->objectives }}</p>
        </section>
    @endif

    <section>
        <h3>{{ __('Attendance') }}</h3>
        @if ($registrationForm)
            <div class="stat-row">
                <div><span class="stat-value">{{ $totalRegistered }}</span><br>{{ __('Total Registered') }}</div>
                @if ($registrationForm->checkin_enabled)
                    <div><span class="stat-value">{{ $checkedInCount }}</span><br>{{ __('Checked In') }}</div>
                    <div><span class="stat-value">{{ $onsiteCount }}</span><br>{{ __('On-site') }}</div>
                @endif
            </div>
        @else
            <p>{{ __('No registration data available.') }}</p>
        @endif
    </section>

    @if ($itineraryItems->isNotEmpty())
        <section>
            <h3>{{ __('Itinerary') }}</h3>
            <table class="itinerary-table">
                <thead>
                    <tr>
                        <th style="width: 8rem;">{{ __('Time') }}</th>
                        <th>{{ __('Activity') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($itineraryItems as $item)
                        <tr>
                            <td>{{ $item->time ?: '—' }}</td>
                            <td>{{ $item->activity ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif

    @if ($reportDetail?->problems)
        <section>
            <h3>{{ __('Problems') }}</h3>
            <p>{{ $reportDetail->problems }}</p>
        </section>
    @endif

    @if ($reportDetail?->achievements)
        <section>
            <h3>{{ __('Achievements') }}</h3>
            <p>{{ $reportDetail->achievements }}</p>
        </section>
    @endif

    <section>
        <h3>{{ __('Financial Summary') }}</h3>
        <table class="finance-table">
            <thead>
                <tr>
                    <th>{{ __('Category') }}</th>
                    <th class="amount">{{ __('Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($incomeByCategory as $category => $amount)
                    <tr>
                        <td>{{ __('Income') }} — {{ $category }}</td>
                        <td class="amount">{{ number_format($amount, 2) }}</td>
                    </tr>
                @endforeach
                @foreach ($expensesByCategory as $category => $amount)
                    <tr>
                        <td>{{ __('Expense') }} — {{ $category }}</td>
                        <td class="amount">({{ number_format($amount, 2) }})</td>
                    </tr>
                @endforeach
                <tr>
                    <td><strong>{{ __('Net Balance') }}</strong></td>
                    <td class="amount"><strong>{{ number_format($netBalance, 2) }}</strong></td>
                </tr>
            </tbody>
        </table>
    </section>

    @if ($reportDetail?->directors_remarks)
        <section>
            <h3>{{ __("Director's Remarks") }}</h3>
            <p>{{ $reportDetail->directors_remarks }}</p>
        </section>
    @endif

    @if ($reportDetail && ($reportDetail->prepared_by_name || $reportDetail->reviewed_by_name || $reportDetail->approved_by_name))
        <div class="signoff-grid">
            <div class="col">
                <p class="role">{{ __('Prepared By') }}</p>
                <div class="signature-space">
                    @if ($reportDetail->preparedBySignatureUrl())
                        <img src="{{ $reportDetail->preparedBySignatureUrl() }}" alt="{{ __('Signature') }}">
                    @endif
                </div>
                <div class="line">
                    <p class="name">{{ $reportDetail->prepared_by_name ?: '—' }}</p>
                    <p class="position">{{ $reportDetail->prepared_by_position }}</p>
                    <p class="date">{{ $reportDetail->prepared_by_date?->format('d M Y') }}</p>
                </div>
            </div>
            <div class="col">
                <p class="role">{{ __('Reviewed By') }}</p>
                <div class="signature-space">
                    @if ($reportDetail->reviewedBySignatureUrl())
                        <img src="{{ $reportDetail->reviewedBySignatureUrl() }}" alt="{{ __('Signature') }}">
                    @endif
                </div>
                <div class="line">
                    <p class="name">{{ $reportDetail->reviewed_by_name ?: '—' }}</p>
                    <p class="position">{{ $reportDetail->reviewed_by_position }}</p>
                    <p class="date">{{ $reportDetail->reviewed_by_date?->format('d M Y') }}</p>
                </div>
            </div>
            <div class="col">
                <p class="role">{{ __('Approved By') }}</p>
                <div class="signature-space">
                    @if ($reportDetail->approvedBySignatureUrl())
                        <img src="{{ $reportDetail->approvedBySignatureUrl() }}" alt="{{ __('Signature') }}">
                    @endif
                </div>
                <div class="line">
                    <p class="name">{{ $reportDetail->approved_by_name ?: '—' }}</p>
                    <p class="position">{{ $reportDetail->approved_by_position }}</p>
                    <p class="date">{{ $reportDetail->approved_by_date?->format('d M Y') }}</p>
                </div>
            </div>
        </div>
    @endif
</body>
</html>
