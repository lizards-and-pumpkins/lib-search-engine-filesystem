<?php

declare(strict_types=1);

namespace LizardsAndPumpkins\DataPool\SearchEngine\Filesystem;

use LizardsAndPumpkins\Context\SelfContainedContext;
use LizardsAndPumpkins\DataPool\SearchEngine\Exception\SearchEngineNotAvailableException;
use LizardsAndPumpkins\DataPool\SearchEngine\FacetFieldTransformation\FacetFieldTransformationRegistry;
use LizardsAndPumpkins\DataPool\SearchEngine\Filesystem\Exception\SearchDocumentCanNotBeStoredException;
use LizardsAndPumpkins\DataPool\SearchEngine\IntegrationTest\AbstractSearchEngineTest;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocument;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocumentFieldCollection;
use LizardsAndPumpkins\DataPool\SearchEngine\SearchEngine;
use LizardsAndPumpkins\Import\Product\ProductId;
use LizardsAndPumpkins\Util\FileSystem\LocalFilesystem;
use \PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * @covers \LizardsAndPumpkins\DataPool\SearchEngine\Filesystem\FileSearchEngine
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterionEqual
 * @uses   \LizardsAndPumpkins\Context\SelfContainedContextBuilder
 * @uses   \LizardsAndPumpkins\Context\SelfContainedContext
 * @uses   \LizardsAndPumpkins\Context\DataVersion\DataVersion
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\CompositeSearchCriterion
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterionEqual
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterionGreaterOrEqualThan
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterionGreaterThan
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterionNotEqual
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterionLessOrEqualThan
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterionLessThan
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterionLike
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchCriteria\SearchCriterionAnything
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocument
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocumentField
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchDocument\SearchDocumentFieldCollection
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\FacetField
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\FacetFieldCollection
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\FacetFieldValue
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\FacetFilterRange
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\FacetFiltersToIncludeInResult
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\FacetFilterRequestRangedField
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\FacetFilterRequestSimpleField
 * @uses   \LizardsAndPumpkins\DataPool\SearchEngine\SearchEngineResponse
 * @uses   \LizardsAndPumpkins\Import\Product\AttributeCode
 * @uses   \LizardsAndPumpkins\Import\Product\ProductId
 * @uses   \LizardsAndPumpkins\Util\FileSystem\LocalFileSystem
 */
class FileSearchEngineTest extends AbstractSearchEngineTest
{
    /**
     * @var string
     */
    private $temporaryStorage;

    /**
     * @var bool
     */
    private static $diskIsFull;

    public static function isDiskFull() : bool
    {
        return self::$diskIsFull;
    }

    final protected function createSearchEngineInstance(
        FacetFieldTransformationRegistry $facetFieldTransformationRegistry
    ) : SearchEngine {
        $this->prepareTemporaryStorage();

        $testSearchableFields = ['baz'];

        return FileSearchEngine::create(
            $this->temporaryStorage,
            $testSearchableFields,
            $facetFieldTransformationRegistry
        );
    }

    protected function setUp()
    {
        self::$diskIsFull = false;
        parent::setUp();
    }

    private function prepareTemporaryStorage()
    {
        $this->temporaryStorage = sys_get_temp_dir() . '/lizards-and-pumpkins-search-engine-storage';

        if (file_exists($this->temporaryStorage)) {
            $localFilesystem = new LocalFilesystem();
            $localFilesystem->removeDirectoryAndItsContent($this->temporaryStorage);
        }

        mkdir($this->temporaryStorage);
    }

    protected function tearDown()
    {
        (new LocalFilesystem())->removeDirectoryAndItsContent($this->temporaryStorage);
    }

    public function testExceptionIsThrownIfSearchEngineStorageDirIsNotAString()
    {
        $this->expectException(\TypeError::class);

        $invalidStoragePath = [];

        /** @var FacetFieldTransformationRegistry|MockObject $stubFacetFieldTransformationRegistry */
        $stubFacetFieldTransformationRegistry = $this->createMock(FacetFieldTransformationRegistry::class);

        $testSearchableFields = [];

        FileSearchEngine::create($invalidStoragePath, $testSearchableFields, $stubFacetFieldTransformationRegistry);
    }

    public function testExceptionIsThrownIfSearchEngineStorageDirIsNotWritable()
    {
        $this->expectException(SearchEngineNotAvailableException::class);

        /** @var FacetFieldTransformationRegistry|MockObject $stubFacetFieldTransformationRegistry */
        $stubFacetFieldTransformationRegistry = $this->createMock(FacetFieldTransformationRegistry::class);

        $testSearchableFields = [];

        FileSearchEngine::create('non-existing-path', $testSearchableFields, $stubFacetFieldTransformationRegistry);
    }

    public function testExceptionIsThrownIfMessageCouldNotBeWritten()
    {
        self::$diskIsFull = true;

        $this->expectException(SearchDocumentCanNotBeStoredException::class);

        /** @var FacetFieldTransformationRegistry|MockObject $stubFacetFieldTransformationRegistry */
        $stubFacetFieldTransformationRegistry = $this->createMock(FacetFieldTransformationRegistry::class);

        $searchEngine = $this->createSearchEngineInstance($stubFacetFieldTransformationRegistry);

        $testSearchDocument = new SearchDocument(
            SearchDocumentFieldCollection::fromArray([]),
            new SelfContainedContext([]),
            new ProductId('foo'))
        ;

        $searchEngine->addDocument($testSearchDocument);
    }
}

/**
 * @param string $filename
 * @param mixed $data
 * @param int $flags
 * @param resource|null $context
 * @return int|bool
 */
function file_put_contents(string $filename, $data, int $flags = 0, $context = null)
{
    if (FileSearchEngineTest::isDiskFull()) {
        return false;
    }

    return \file_put_contents($filename, $data, $flags, $context);
}
