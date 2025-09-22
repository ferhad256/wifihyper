<?php

namespace App\Console\Commands;

use App\Models\Hotspot;
use Illuminate\Console\Command;

class FixHotspotUrlNames extends Command
{
    protected $signature = 'hotspots:fix-url-names';
    protected $description = 'Generate url_name for hotspots that don\'t have one';

    public function handle()
    {
        $this->info('Fixing hotspot URL names...');
        
        $hotspotsWithoutUrlName = Hotspot::whereNull('url_name')->get();
        
        if ($hotspotsWithoutUrlName->isEmpty()) {
            $this->info('All hotspots already have URL names.');
            return;
        }
        
        $this->info("Found {$hotspotsWithoutUrlName->count()} hotspots without URL names.");
        
        foreach ($hotspotsWithoutUrlName as $hotspot) {
            $urlName = Hotspot::generateUrlName($hotspot->name, $hotspot->id);
            $hotspot->url_name = $urlName;
            $hotspot->save();
            
            $this->line("✓ Fixed hotspot '{$hotspot->name}' -> URL: {$urlName}");
        }
        
        $this->info('All hotspot URL names have been fixed!');
    }
}
