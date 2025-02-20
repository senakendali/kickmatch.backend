<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\Exportable;
use App\Models\TeamMember;

class TeamMembersExport implements FromCollection, WithHeadings
{
    use Exportable;

    // ✅ Tambahkan judul kolom di sini
    public function headings(): array
    {
        return [
            'id', 
            'Nama', 
            'Tempat Lahir', 
            'Tanggal Lahir',
            'Jenis Kelamin',
            'Tinggi Badan',
            'Berat Badan',
            'NIK',
            'Nomor Kartu Keluarga',
            'Alamat'
        ];
    }

    public function collection()
    {
        return TeamMember::select('id', 'name', 'birth_place', 'birth_date', 'gender', 'body_height', 'body_weight', 'nik', 'family_card_number', 'address')->get();
    }
}

