<?php

namespace Bolt\Extension\TwoKings\IsUseful;

use Bolt\Asset\File\JavaScript;
use Bolt\Asset\File\Stylesheet;
use Bolt\Controller\Zone;
use Bolt\Extension\DatabaseSchemaTrait;
use Bolt\Extension\SimpleExtension;
use Bolt\Extension\TwoKings\IsUseful\Config\Config;
use Bolt\Extension\TwoKings\IsUseful\EventListener\FormListener;
use Silex\Application;
use Silex\ControllerCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @author Xiao-Hu Tai <xiao@twokings.nl>
 */
class Extension extends SimpleExtension
{
    use DatabaseSchemaTrait;

    /**
     * {@inheritdoc}
     */
    // protected function subscribe(EventDispatcherInterface $dispatcher)
    // {
    //     $dispatcher->addSubscriber(new FormListener($this->getContainer()));
    // }

    /**
     * {@inheritdoc}
     */
    protected function registerExtensionTables()
    {
        return [
            'is_useful' => IsUsefulTable::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function registerFrontendRoutes(ControllerCollection $collection)
    {
        // Update with a 'yes' or a 'no' and count them in the database
        $collection
            ->post('/async/is-useful', [$this, 'updateStatistics'])
            ->bind('is_useful.update')
        ;
    }

    /**
     * @param Application $app
     * @param Request     $request
     */
    public function updateStatistics(Application $app, Request $request)
    {
        $contenttype = $request->request->get('contenttype');
        $id          = $request->request->get('contentid');
        $type        = $request->request->get('type'); // 'yes' or 'no'

        if (!$contenttype || !$id || !in_array($type, ['yes', 'no'])) {
            return "One or more values are undefined: contenttype: [$contenttype] id: [$id] type: [$type] value: [$value]";
        }

        // todo: move this to a factory / server
        $stats = new Stats(
            $app['db'],
            $contenttype,
            $id
        );
        $stats->set(
            $request->getClientIp(),
            $type
        );

        return 'ok!';
    }

    /**
     * {@inheritdoc}
     */
    protected function registerTwigPaths()
    {
        return [
            'templates' => [
                'position'  => 'prepend',
                'namespace' => 'is_useful'
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function registerAssets()
    {
        $assets = [];

        $config = $this->getConfig();

        // By default, add JavaScript and CSS files, unless it is explicityly
        // set to `false`.
        if (!isset($config['add_js']) || $config['add_js'] !== false) {
            $assets[] = JavaScript::create()
                ->setFileName('extensions/vendor/twokings/is-useful/extension.js')
                ->setLate(true)
                ->setZone(Zone::FRONTEND)
            ;
        }

        if (!isset($config['add_css']) || $config['add_css'] !== false) {
            $assets[] = StyleSheet::create()
                ->setFileName('extensions/vendor/twokings/is-useful/extension.css')
                ->setLate(true)
                ->setZone(Zone::FRONTEND)
            ;
        }

        return $assets;
    }

    /**
     * {@inheritdoc}
     */
    protected function registerServices(Application $app)
    {
        $this->extendDatabaseSchemaServices();

        $app['is_useful.config'] = $app->share(function () { return new Config($this->getConfig()); });
    }
}
