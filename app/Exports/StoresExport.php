<?php

namespace App\Exports;

use App\Models\ScrapedStore;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class StoresExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function query()
    {
        $query = ScrapedStore::query()->where('is_found', true);

        if ($this->request->filled('search')) {
            $search = $this->request->search;
            $query->where(function ($q) use ($search) {
                $q->where('store_name', 'like', "%{$search}%")
                  ->orWhere('domain', 'like', "%{$search}%")
                  ->orWhereRaw('CAST(contacts AS CHAR) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('CAST(full_settings AS CHAR) LIKE ?', ["%{$search}%"]);
            });
        }

        if ($this->request->filled('maintenance')) {
            if ($this->request->maintenance === 'yes') {
                $query->where('full_settings->data->maintenance', true);
            } elseif ($this->request->maintenance === 'no') {
                $query->where(function ($q) {
                    $q->where('full_settings->data->maintenance', false)
                      ->orWhereNull('full_settings->data->maintenance');
                });
            }
        }

        return $query->orderBy('id', 'desc');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Store Name',
            'URL / Domain',
            'Freelance Number',
            'WhatsApp',
            'Mobile',
            'Maintenance',
            'Theme Name',
            'Theme Mode',
            'Loyalty System',
            'Description',
            'Scraped At',
        ];
    }

    public function map($store): array
    {
        $settings = is_array($store->full_settings) ? $store->full_settings : json_decode($store->full_settings, true);
        $data = $settings['data'] ?? [];
        
        $url = $data['store']['url'] ?? "https://{$store->domain}";
        $freelance_number = $data['store']['settings']['freelance_number'] ?? '';
        $maintenance = ($data['maintenance'] ?? false) ? 'Yes' : 'No';
        
        $theme_name = $data['theme']['name'] ?? '';
        $theme_mode = $data['theme']['mode'] ?? '';
        
        $contacts = is_array($store->contacts) ? $store->contacts : json_decode($store->contacts, true) ?? [];
        $whatsapp = $contacts['whatsapp'] ?? '';
        $mobile = $contacts['mobile'] ?? '';

        $features = $data['store']['features'] ?? [];
        $loyalty = in_array('loyalty-system-v2', $features) || in_array('loyalty-system', $features) ? 'Yes' : 'No';

        return [
            $store->id,
            $store->store_name,
            $url,
            $freelance_number,
            $whatsapp,
            $mobile,
            $maintenance,
            $theme_name,
            $theme_mode,
            $loyalty,
            $store->store_description,
            $store->created_at->format('Y-m-d H:i:s'),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '0F172A'], // Tailwind Slate 900
                ],
                'borders' => [
                    'bottom' => [
                        'borderStyle' => Border::BORDER_MEDIUM,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ],
        ];
    }
}
