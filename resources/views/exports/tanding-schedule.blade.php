<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Jadwal Pertandingan</title>
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

        .dark{
            background-color:#495057;
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

@php
    // Gabungkan semua entry dengan arena_name dan scheduled_date yang sama
    $groupedArenas = collect($data)->groupBy(function($item) {
        return $item['arena_name'] . '|' . $item['scheduled_date'];
    });
@endphp

@foreach ($groupedArenas as $key => $entries)
    @php
        $first = $entries->first();
        $matches = $entries->flatMap(function ($entry) {
            return $entry['matches'];
        });
    @endphp

    <div class="mt-3">
        <!-- Header -->
        <table style="width: 100%; margin-bottom: 10px;">
            <tr>
                <td style="width: 25%;">
                    <img src="{{ public_path('images/ipsi.png') }}" class="logo">
                </td>
                <td style="width: 50%; text-align: center;">
                    <h4 class="uppercase fw-bold">JADWAL {{ $first['arena_name'] }}</h4>
                    <h4 class="uppercase fw-bold">{{ $first['tournament_name'] }}</h4>
                    <div class="uppercase fw-bold">
                        {{ \Carbon\Carbon::parse($first['scheduled_date'])->translatedFormat('d F Y') }}
                    </div>
                </td>
                <td style="width: 25%;"></td>
            </tr>
        </table>

        <!-- Tabel semua pertandingan langsung -->
        <table class="table">
            <thead>
                <tr>
                    <th class="dark">PARTAI</th>
                    <th class="dark">BABAK</th>
                    <th class="uppercase text-center dark">KELAS</th>
                    <th class="dark">POOL</th>
                    <th class="uppercase text-center blue-corner">SUDUT BIRU</th>
                    <th class="uppercase text-center red-corner">SUDUT MERAH</th>
                    <th colspan="2" class="text-center dark">SCORE</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($matches as $match)
                    <tr>
                        <td>{{ $match['match_number'] }}</td>
                        <td>{{ $match['round_label'] }}</td>
                        <td class="text-center">{{ $match['class_name'] ?? '-' }}</td>
                        <td>{{ $match['pool_name'] ?? '-' }}</td>
                        <td class="text-center">
                            <div class="blue">{{ $match['participant_one'] }}</div>
                            <div class="text-success">{{ $match['contingent_one'] ?? '-' }}</div>
                        </td>
                        <td class="text-center">
                            <div class="red">{{ $match['participant_two'] }}</div>
                            <div class="text-success">{{ $match['contingent_two'] ?? '-' }}</div>
                        </td>
                        <td class="text-center">-</td>
                        <td class="text-center">-</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endforeach







</body>
</html>
