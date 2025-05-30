<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Jadwal Pertandingan Seni</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 12px;
            color: #333;
        }

        h4, h5 {
            margin: 0;
            padding: 0;
            font-size:18px;
        }

        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .fw-bold { font-weight: bold; }
        .blue { color: #007bff; }
        .red { color: #dc3545; }
        .text-success { color: #28a745; }
        .uppercase { text-transform: uppercase; }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 40px;
        }
        .table th, .table td {
            padding: 6px;
            font-size: 11px;
        }
        .table th {
            
            font-weight: bold;
        }

        .table tbody tr:nth-child(even) {
            background-color: #f2f2f2;
        }


        .logo {
            width: 120px;
        }

        .header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .header-col {
            flex: 1;
        }

        .header-center {
            text-align: center;
        }

        .header-right {
            text-align: right;
        }

        .mt-3 { margin-top: 1rem; }

        .table td,
        .table th {
            padding: 20px; /* atas-bawah 8px, kiri-kanan 6px */
        }

        .table th {
           text-transform:uppercase;
        }

        .soft-dark{
            background-color:#495057;
            color:#FFFFFF;
        }

        .dark{
            background-color:#343A40;
            color:#FFFFFF;
        }

        .blue-corner{
            background-color:#002FB9;
            color:#FFFFFF;
        }

        .red-corner{
            background-color:#F80000;
            color:#FFFFFF;
        }

    </style>
</head>
<body>

    

    <table style="width: 100%; margin-bottom: 10px;">
        <tr>
            <td style="width: 25%;">
            <img src="{{ public_path('images/ipsi.png') }}" class="logo">
            </td>
            <td style="width: 50%; text-align: center;">
            <h4 class="uppercase fw-bold">JADWAL {{ $data['arena_name'] }}</h4>
            <h4 class="uppercase fw-bold">{{ $data['tournament_name'] }}</h4>
            <div class="uppercase fw-bold">
                {{ \Carbon\Carbon::parse($data['scheduled_date'])->translatedFormat('d F Y') }}
            </div>
            </td>
            <td style="width: 25%;"></td>
        </tr>
    </table>

    @foreach ($data['groups'] as $group)
        @foreach ($group['pools'] as $pool)
           
            <table class="table">
                <thead>
                    <tr>
                        <th class="soft-dark">Partai</th>
                        <th class="soft-dark">Kontingen</th>
                        <th colspan="3" class="soft-dark">Nama Atlet</th>
                        <th class="soft-dark">Waktu</th>
                        <th class="soft-dark">Score</th>
                    </tr>
                     <tr>
                        <th colspan="7" class="dark">
                        {{ $group['category'] }} {{ $group['gender'] === 'male' ? 'PUTRA' : 'PUTRI' }} - {{ $group['age_category'] }} - {{ $pool['name'] }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pool['matches'] as $match)
                        <tr>
                            <td>{{ $match['match_order'] }}</td>
                            <td>{{ $match['contingent']['name'] ?? '-' }}</td>

                            @if ($match['match_type'] === 'seni_tunggal')
                                <td>{{ $match['team_member1']['name'] ?? '-' }}</td>
                                <td colspan="2">-</td>
                            @elseif ($match['match_type'] === 'seni_ganda')
                                <td>{{ $match['team_member1']['name'] ?? '-' }}</td>
                                <td>{{ $match['team_member2']['name'] ?? '-' }}</td>
                                <td>-</td>
                            @elseif ($match['match_type'] === 'seni_regu')
                                <td>{{ $match['team_member1']['name'] ?? '-' }}</td>
                                <td>{{ $match['team_member2']['name'] ?? '-' }}</td>
                                <td>{{ $match['team_member3']['name'] ?? '-' }}</td>
                            @endif

                            <td>{{ $match['match_time'] ?? '-' }}</td>
                            <td>{{ $match['final_score'] ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    @endforeach

</body>
</html>
