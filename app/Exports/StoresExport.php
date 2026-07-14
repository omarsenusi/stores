<?php

namespace App\Exports;

use App\Models\ScrapedStore;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class StoresExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithColumnWidths
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
        
        // Add a space before phone numbers to force Excel to treat them as text and avoid scientific notation (e.g. 9.66E+11)
        $whatsapp = $whatsapp ? ' ' . $whatsapp : '';
        $mobile = $mobile ? ' ' . $mobile : '';

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

    public function columnWidths(): array
    {
        return [
            'A' => 10, // ID
            'B' => 30, // Store Name
            'C' => 45, // URL / Domain
            'D' => 20, // Freelance Number
            'E' => 20, // WhatsApp
            'F' => 20, // Mobile
            'G' => 15, // Maintenance
            'H' => 25, // Theme Name
            'I' => 15, // Theme Mode
            'J' => 15, // Loyalty System
            'K' => 50, // Description
            'L' => 22, // Scraped At
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Add AutoFilter to the first row (A to L)
        $sheet->setAutoFilter('A1:L1');

        // Apply alignment to all cells
        $sheet->getStyle('A2:L'.$sheet->getHighestRow())
              ->getAlignment()
              ->setVertical(Alignment::VERTICAL_CENTER)
              ->setWrapText(true); // Allow description to wrap if it's too long

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
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }
}
