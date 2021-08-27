<?php 
namespace GaNuongLaChanh\Sonic\Console;
use Flarum\Console\AbstractCommand;
use Illuminate\Contracts\Container\Container;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;

class AddToIndex extends AbstractCommand
{
    protected $container;
    protected $database;

    public function __construct(Container $container, SettingsRepositoryInterface $settings)
    {
        parent::__construct();
        $this->container = $container;
        $this->settings = $settings;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('sonic:addtoindex')
            ->setDescription('Add data to sonic index');
    }

    /**
     * {@inheritdoc}
     */
    protected function fire()
    {
        $this->info('Starting...');

        //$config = $this->container->make('flarum.config');
        //$prefix = $config['database']['prefix'];
        $host = $this->settings->get('ganuonglachanh-sonic.host','sonic');
        $port = (int) $this->settings->get('ganuonglachanh-sonic.port', 1491);
        $timeout = (int) $this->settings->get('ganuonglachanh-sonic.timeout', 30);
        $locale = $this->settings->get('ganuonglachanh-sonic.locale','eng');
        //https://github.com/ppshobi/psonic/blob/master/api-docs.md
        $ingest  = new \Psonic\Ingest(new \Psonic\Client($host, $port, $timeout));
        $control = new \Psonic\Control(new \Psonic\Client($host, $port, $timeout));
        $ingest->connect('SecretPassword');
        $control->connect('SecretPassword');
        echo 'Flush old postCollection: ' . $ingest->flushc('postCollection') . PHP_EOL;
        echo "Adding to index...". PHP_EOL;
        Post::select('id','content')
        ->where('type','=', 'comment')
        ->where('is_approved', 1)
        ->where('is_private', 0)
        ->whereNull('hidden_at')
        ->chunk(200, function ($posts) use ($ingest, $locale) {
            foreach ($posts as $post) {
                //echo json_encode($post->content); exit(0);
                //echo $ingest->push('postCollection', 'flarumBucket', $post->id,$post->content)->getStatus(); // OK
                $ingest->push('postCollection', 'flarumBucket', $post->id,$post->content, $locale);
            }
        });
        echo $control->consolidate(); // saves the data to disk
        $ingest->disconnect();
        $control->disconnect();
        //echo json_encode($result);
        echo "\nDone!". PHP_EOL;
    }
}