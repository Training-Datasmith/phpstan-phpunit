<?php

declare (strict_types=1);
namespace Php_Stan\Rules\Php_Unit;

use Php_Stan\Parser\Parser;
use Php_Stan\Reflection\Reflection_Provider;
use Php_Stan\Type\File_Type_Mapper;
class Data_Provider_Helper_Factory
{
    private Reflection_Provider $reflection_provider;
    private File_Type_Mapper $file_type_mapper;
    private Parser $parser;
    private Php_Unit_Version $php_unit_version;
    public function __construct(Reflection_Provider $reflection_provider, File_Type_Mapper $file_type_mapper, Parser $parser, Php_Unit_Version $php_unit_version)
    {
        $this->reflection_provider = $reflection_provider;
        $this->file_type_mapper = $file_type_mapper;
        $this->parser = $parser;
        $this->php_unit_version = $php_unit_version;
    }
    public function create(): Data_Provider_Helper
    {
        return new Data_Provider_Helper($this->reflection_provider, $this->file_type_mapper, $this->parser, $this->php_unit_version);
    }
}