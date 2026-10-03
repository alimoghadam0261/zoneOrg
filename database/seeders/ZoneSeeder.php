<?php

namespace Database\Seeders;

use App\Models\Zone;
use App\Services\Geofencing\ZoneIndex;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class ZoneSeeder extends Seeder
{
    /**
     * Demo zones around the default map centre (Tehran).
     */
    public function run(): void
    {
        $zones = [
            [
                'name' => 'سوله سوخت',
                'code' => 'SOULEH_01',
                'type' => 'polygon',
                'severity_level' => 'critical',
                'color' => '#ef4444',
                'description' => 'انبار سوخت — ورود بدون همراه ناظر HSE ممنوع.',
                'center' => [35.6925, 51.3910],
                'span' => [0.0032, 0.0040],
                'default_access' => 'deny',
                'rules' => [
                    ['target_type' => 'department', 'target_value' => 'تعمیرات برق', 'access_type' => 'allow'],
                    ['target_type' => 'department', 'target_value' => 'HSE', 'access_type' => 'allow'],
                ],
            ],
            [
                'name' => 'سالن توربین',
                'code' => 'TURBINE_HALL',
                'type' => 'rectangle',
                'severity_level' => 'high',
                'color' => '#f59e0b',
                'description' => 'سالن توربین و ژنراتور — ورود پیمانکاران فقط با مجوز.',
                'center' => [35.6868, 51.3955],
                'span' => [0.0026, 0.0035],
                'default_access' => 'allow',
                'rules' => [
                    ['target_type' => 'contract_type', 'target_value' => 'visitor', 'access_type' => 'deny'],
                ],
            ],
            [
                'name' => 'مخزن مواد شیمیایی',
                'code' => 'CHEM_STORE',
                'type' => 'circle',
                'severity_level' => 'critical',
                'color' => '#dc2626',
                'description' => 'مخزن مواد شیمیایی — محدوده کاملاً ممنوع.',
                'center' => [35.6835, 51.3842],
                'radius' => 140,
                'default_access' => 'deny',
                'rules' => [
                    ['target_type' => 'department', 'target_value' => 'HSE', 'access_type' => 'allow'],
                ],
            ],
            [
                'name' => 'اداره مرکزی',
                'code' => 'ADMIN_01',
                'type' => 'polygon',
                'severity_level' => 'low',
                'color' => '#16a34a',
                'description' => 'ساختمان اداری — محدوده مجاز.',
                'center' => [35.6875, 51.3825],
                'span' => [0.0020, 0.0028],
                'default_access' => 'allow',
                'rules' => [],
            ],
            [
                'name' => 'محوطه نیروگاه',
                'code' => 'PERIMETER_01',
                'type' => 'polygon',
                'severity_level' => 'medium',
                'color' => '#6366f1',
                'description' => 'کل محوطه — محدوده اصلی نیروگاه.',
                'center' => [35.6892, 51.3890],
                'span' => [0.0090, 0.0120],
                'default_access' => 'allow',
                'rules' => [],
            ],
        ];

        foreach ($zones as $data) {
            $geometry = $this->geometry($data);
            $bbox = \App\Services\Geofencing\GeoJson::bbox($geometry);

            $zone = Zone::query()->updateOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'type' => $data['type'],
                    'severity_level' => $data['severity_level'],
                    'color' => $data['color'],
                    'description' => $data['description'],
                    'is_active' => true,
                    'geometry' => $geometry,
                    'min_lat' => $bbox[0],
                    'min_lng' => $bbox[1],
                    'max_lat' => $bbox[2],
                    'max_lng' => $bbox[3],
                    'center_lat' => $data['type'] === 'circle' ? $data['center'][0] : null,
                    'center_lng' => $data['type'] === 'circle' ? $data['center'][1] : null,
                    'radius' => $data['type'] === 'circle' ? $data['radius'] : null,
                ]
            );

            $zone->syncBoundingBox();
            $zone->save();

            $zone->accessRules()->delete();

            $zone->accessRules()->create([
                'target_type' => 'person',
                'target_id' => null,
                'target_value' => '*',
                'access_type' => $data['default_access'] === 'deny' ? 'deny' : 'allow',
            ]);

            foreach ($data['rules'] as $rule) {
                $zone->accessRules()->create($rule + ['target_id' => null]);
            }
        }

        Cache::forget(ZoneIndex::CACHE_KEY);
        app(ZoneIndex::class)->forget();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function geometry(array $data): array
    {
        if ($data['type'] === 'circle') {
            [$lat, $lng] = $data['center'];

            return [
                'type' => 'Point',
                'coordinates' => [round($lng, 8), round($lat, 8)],
                'properties' => ['radius' => $data['radius']],
            ];
        }

        [$lat, $lng] = $data['center'];
        [$dLat, $dLng] = $data['span'];

        $ring = [
            [round($lng - $dLng, 8), round($lat - $dLat, 8)],
            [round($lng + $dLng, 8), round($lat - $dLat, 8)],
            [round($lng + $dLng, 8), round($lat + $dLat, 8)],
            [round($lng - $dLng, 8), round($lat + $dLat, 8)],
            [round($lng - $dLng, 8), round($lat - $dLat, 8)],
        ];

        return ['type' => 'Polygon', 'coordinates' => [$ring]];
    }
}
