<?php

namespace Cherry\Template;

use ReflectionObject;
use InvalidArgumentException;
use League\Plates\Engine;
use League\Plates\Template\Template;
use League\Plates\Extension\ExtensionInterface;

class LangExtension implements ExtensionInterface
{
    /**
     * @var Template
     */
    public $template;

    /**
     * @var Engine
     */
    protected $engine;

    /**
     * @var string
     */
    protected $langPath;


    /**
     * @var string
     */
    protected $langFile;

    /**
     * @var array
     */
    protected $langData;

    public function register(Engine $engine)
    {
        $this->engine = $engine;
        $this->engine->registerFunction('lang', [$this, 'lang']);
    }

    public function setLang(string $path, string $lang)
    {
        if (!is_dir($path)) {
            throw new InvalidArgumentException('Path does not exist: ' . $path);
        }
        $path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $lang = trim($lang, DIRECTORY_SEPARATOR);
        if (!is_dir($path . $lang)) {
            throw new InvalidArgumentException('Language directory does not exist: ' . $lang);
        }
        $this->langPath = $path . $lang . DIRECTORY_SEPARATOR;

        return $this;
    }

    public function lang(string $key, ...$args): string
    {
        $langData = $this->getLangData();
        if (!isset($langData[$key])) {
            return '';
        }
        $plural = false;
        if (count($args) === 2 && is_bool($args[1])) {
            $plural = $args[1];
            $args = is_array($args[0]) ? $args[0] : [$args[0]];
        } else if (count($args) === 1 && is_bool($args[0])){
            $plural = $args[0];
            $args = [];
        }
        if (is_string($langData[$key])) {
            $format = $langData[$key];
        } else if (is_array($langData[$key])) {
            if ($plural && isset($langData[$key][1])) {
                $format = $langData[$key][1];
            } else {
                $format = $langData[$key][0];
            }
        } else {
            throw new InvalidArgumentException(sprintf('Invalid lang string type: %s',  gettype($langData[$key])));
        }
        if (empty($args)) {
            return sprintf($format);
        } else {
            return sprintf($format, ...$args);
        }
    }

    protected function getLangData(): array
    {
        $fileName = $this->getLangFile();
        if (!is_null($this->langData) && $fileName === $this->langFile) {
            return $this->langData;
        }
        $this->langFile = $fileName;
        $file = $this->langPath . $fileName . '.php';
        if (!file_exists($file)) {
            $this->langData = [];
        } else {
            $this->langData = require $file;
        }

        return $this->langData;
    }

    protected function getLangFile()
    {
        $reflectTemplate = new ReflectionObject($this->template);
        $reflectTemplateName = $reflectTemplate->getProperty('name');
        $reflectTemplateName->setAccessible(true);
        $name = $reflectTemplateName->getValue($this->template);
        return $name->getName();
    }
}
