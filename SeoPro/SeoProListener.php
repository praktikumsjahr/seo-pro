<?php

namespace Statamic\Addons\SeoPro;

use Statamic\API\Str;
use Statamic\API\Nav;
use Statamic\API\File;
use Statamic\API\YAML;
use Statamic\API\Collection;
use Statamic\Extend\Listener;

class SeoProListener extends Listener
{
    use TranslatesFieldsets;

    public $events = [
        'cp.nav.created' => 'addNavItems',
        \Statamic\Events\Data\FindingFieldset::class => 'addFieldsetTab',
        \Statamic\Events\RoutesMapping::class => 'addRoutes',
        'cp.add_to_head' => 'addToHead',
    ];

    public function addNavItems($nav)
    {
        $seo = Nav::item('seo-pro')
            ->title('SEO Pro')
            ->route('seopro.dashboard')
            ->icon('magnifying-glass');

        $seo->add(function ($item) {
            $item->add(Nav::item('seo-pro-defaults')
                ->title('Site Defaults')
                ->route('seopro.defaults.edit'));

            $item->add(Nav::item('seo-pro-content')
                ->title('Content Defaults')
                ->route('seopro.content.index'));

            $item->add(Nav::item('seo-pro-humans')
                ->title('Humans.txt')
                ->route('seopro.humans.edit'));

            $item->add(Nav::item('seo-pro-reports')
                ->title('Reports')
                ->route('seopro.reports.index'));

            $item->add(Nav::item('seo-pro-settings')
                ->title('Settings')
                ->route('addon.settings', ['addon' => 'seo-pro']));
        });

        $nav->addTo('tools', $seo);
    }

    public function addFieldsetTab($event)
    {
        if (! $this->shouldHaveSeoTab($event->data)) {
            return;
        }

        $fieldset = $event->fieldset;
        $sections = $fieldset->sections();

        $fields = YAML::parse(File::get($this->getDirectory().'/resources/fieldsets/content.yaml'))['fields'];

        $seoFields = collect($fields['seo']['fields'])->map(function ($field, $key) use ($event) {
            $field['placeholder'] = $this->getPlaceholder($key, $field, $event->data);
            return $field;
        })->all();

        $fields['seo']['fields'] = $this->translateFieldsetFields($seoFields, 'content');

        $sections['seo'] = [
            'display' => 'SEO',
            'fields' => $fields
        ];

        $contents = $fieldset->contents();
        $contents['sections'] = $sections;
        $fieldset->contents($contents);
    }

    protected function getPlaceholder($key, $field, $data)
    {
        $vars = (new TagData)
            ->with(Settings::load()->get('defaults'))
            ->with($data->getWithCascade('seo', []))
            ->withCurrent($data)
            ->get();

        return array_get($vars, $key);
    }

    protected function shouldHaveSeoTab($model)
    {
        $classes = [
            \Statamic\Contracts\Data\Pages\Page::class,
            \Statamic\Contracts\Data\Entries\Entry::class,
            \Statamic\Contracts\Data\Taxonomies\Term::class
        ];

        foreach ($classes as $class) {
            if ($model instanceof $class) {
                return true;
            }
        }

        return false;
    }

    public function addRoutes($event)
    {
        if (site_locale() === default_locale()) {
            $event->router->get($this->getConfig('sitemap_url'), 'Statamic\Addons\SeoPro\Controllers\SitemapController@show');
        }
    }

    public function addToHead()
    {
        $assetContainer = $this->getConfig('asset_container');

        return "<script>var SeoPro = { assetContainer: '{$assetContainer}' };</script>";
    }
}
