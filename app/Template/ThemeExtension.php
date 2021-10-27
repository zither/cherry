<?php

namespace Cherry\Template;

use Psr\Container\ContainerInterface;
use InvalidArgumentException;
use League\Plates\Engine;
use League\Plates\Extension\ExtensionInterface;

class ThemeExtension implements ExtensionInterface
{
    /**
     * @var Engine
     */
    protected $engine;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var string
     */
    protected $themePath;

    /**
     * @var string
     */
    protected $themeName;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function register(Engine $engine)
    {
        $this->engine = $engine;
        $this->engine->registerFunction('getThemeName', [$this, 'getThemeName']);
    }

    public function setThemePath(string $path)
    {
        if (!is_dir($path)) {
            throw new InvalidArgumentException('Path does not exist: ' . $path);
        }
        $this->themePath = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return $this;
    }

    public function getThemeName(): string
    {
        if (!empty($this->themeName)) {
            return $this->themeName;
        }
        $settings = $this->container->make('settings', ['keys' => ['theme']]);
        $file = sprintf('%s%s.css', $this->themePath, $settings['theme']);
        $this->themeName = file_exists($file) ? $settings['theme'] : 'default';
        return $this->themeName;
    }
}
