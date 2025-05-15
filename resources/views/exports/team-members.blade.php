<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Team Members</title>
</head>
<body>
    <table border="1">
        <thead>
            <tr>
                <th>ID</th>
                <th>Contingent</th>
                <th>Nama</th>
                <th>Tempat Lahir</th>
                <th>Tanggal Lahir</th>
                <th>Jenis Kelamin</th>
                <th>Tinggi Badan</th>
                <th>Berat Badan</th>
                <th>NIK</th>
                <th>No. KK</th>
                <th>Alamat</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($teamMembers as $m)
                <tr>
                    <td>{{ $m->id }}</td>
                    <td>{{ $m->contingent->name ?? '-' }}</td>
                    <td>{{ $m->name }}</td>
                    <td>{{ $m->birth_place }}</td>
                    <td>{{ $m->birth_date }}</td>
                    <td>{{ $m->gender }}</td>
                    <td>{{ $m->body_height }}</td>
                    <td>{{ $m->body_weight }}</td>
                    <td>{{ $m->nik }}</td>
                    <td>{{ $m->family_card_number }}</td>
                    <td>{{ $m->address }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
