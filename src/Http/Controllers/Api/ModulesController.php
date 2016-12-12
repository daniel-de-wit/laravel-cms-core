<?php
namespace Czim\CmsCore\Http\Controllers\Api;

use Czim\CmsCore\Api\Response\TransformContainer;
use Czim\CmsCore\Api\Transformers\ModuleTransformer;
use Czim\CmsCore\Contracts\Core\CoreInterface;
use Czim\CmsCore\Contracts\Modules\ModuleManagerInterface;
use Czim\CmsCore\Http\Controllers\Controller;
use Illuminate\Contracts\Support\Arrayable;

class ModulesController extends Controller
{

    /**
     * @var ModuleManagerInterface
     */
    protected $modules;

    /**
     * @param CoreInterface          $core
     * @param ModuleManagerInterface $modules
     */
    public function __construct(CoreInterface $core, ModuleManagerInterface $modules)
    {
        parent::__construct($core);

        $this->modules = $modules;
    }


    /**
     * Returns a list of modules currently loaded.
     *
     * @return mixed
     */
    public function index()
    {
        return $this->core->api()->response(
            $this->makeContainer($this->modules->getModules())
        );
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function show($key)
    {
        $module = $this->modules->get($key);

        if ( ! $module) {
            abort(404, "Module not found or not loaded");
        }

        return $this->core->api()->response(
            $this->makeContainer($module, false)
        );
    }


    /**
     * Wraps data in a transform container.
     *
     * @param array|Arrayable $data
     * @param bool            $collection
     * @return TransformContainer
     */
    protected function makeContainer($data, $collection = true)
    {
        return new TransformContainer([
            'content'     => $data,
            'transformer' => new ModuleTransformer,
            'collection'  => $collection,
        ]);
    }

}
