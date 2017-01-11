<?php
namespace CPSIT\MaskExport\Tests\Functional\Controller;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Nicole Cordes <typo3@cordes.co>, CPS-IT GmbH
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use CPSIT\MaskExport\Controller\ExportController;
use TYPO3\CMS\Core\Tests\FunctionalTestCase;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Web\Request;
use TYPO3\CMS\Extbase\Mvc\Web\Response;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Fluid\View\TemplateView;

class ExportControllerTest extends FunctionalTestCase
{
    /**
     * @var array
     */
    protected $configurationToUseInTestInstance = [
        'EXT' => [
            'extConf' => [
                'mask' => 'a:1:{s:4:"json";s:61:"typo3conf/ext/mask_export/Tests/Functional/Fixtures/mask.json";}',
                'mask_export' => 'a:2:{s:14:"backendPreview";s:1:"1";s:19:"contentElementIcons";s:1:"1";}',
            ],
        ],
    ];

    /**
     * @var array
     */
    protected $files = [];

    /**
     * @var array
     */
    protected $testExtensionsToLoad = [
        'typo3conf/ext/mask',
        'typo3conf/ext/mask_export',
    ];

    /**
     * Set up the subject under test
     */
    protected function setUp()
    {
        parent::setUp();

        $objectManager = new ObjectManager();

        $viewMock = $objectManager->get(TemplateView::class);
        $viewMock->setLayoutRootPaths(['EXT:mask_export/Resources/Private/Backend/Layout']);
        $viewMock->setPartialRootPaths(['EXT:mask_export/Resources/Private/Backend/Partials']);
        $viewMock->setTemplateRootPaths(['EXT:mask_export/Resources/Private/Backend/Templates']);
        GeneralUtility::addInstance(TemplateView::class, $viewMock);

        $request = new Request();
        $request->setControllerVendorName('CPSIT');
        $request->setControllerExtensionName('mask_export');
        $request->setControllerName('Export');
        $request->setControllerActionName('list');
        $response = new Response();

        $subject = $objectManager->get(ExportController::class);
        $subject->processRequest($request, $response);

        $variables = $viewMock->getRenderingContext()->getVariableProvider();
        $this->files = $variables->getByPath('files');
    }

    /**
     * @test
     */
    public function checkFluidTemplatePathsInTypoScript()
    {
        $this->assertArrayHasKey('Configuration/TypoScript/setup.ts', $this->files);
        $templatePaths = [];
        preg_match_all(
            '#templateRootPaths\\.0 = EXT:mask_export/(.+)$#m',
            $this->files['Configuration/TypoScript/setup.ts'],
            $templatePaths,
            PREG_SET_ORDER
        );
        $this->assertNotEmpty($templatePaths);
        $templateNames = [];
        preg_match_all(
            '#templateName = (.+)$#m',
            $this->files['Configuration/TypoScript/setup.ts'],
            $templateNames,
            PREG_SET_ORDER
        );
        $this->assertCount(count($templatePaths), $templateNames);
        foreach ($templatePaths as $key => $templatePathArray) {
            $templatePath = $templatePathArray[1] . $templateNames[$key][1] . '.html';
            $this->assertArrayHasKey($templatePath, $this->files);
        }
    }

    /**
     * @test
     */
    public function checkFluidTemplatePathInBackendPreview()
    {
        $this->assertArrayHasKey('Classes/Hooks/PageLayoutViewDrawItem.php', $this->files);
        $matches = [];
        preg_match(
            '#\\$templatePath = GeneralUtility::getFileAbsFileName\\(([^)]+)\\);#',
            $this->files['Classes/Hooks/PageLayoutViewDrawItem.php'],
            $matches
        );
        $this->assertCount(2, $matches);
        $templateRootPath = str_replace(
            [
                '\'',
                ' . ',
                '$this->rootPath',
            ],
            [
                '',
                '',
                'Resources/Private/Backend/',
            ],
            $matches[1]
        );
        $matches = [];
        preg_match(
            '#protected \\$supportedContentTypes = ([^;]+);#',
            $this->files['Classes/Hooks/PageLayoutViewDrawItem.php'],
            $matches
        );
        $this->assertCount(2, $matches);
        $supportedContentTypes = eval('return ' . $matches[1] . ';');
        foreach ($supportedContentTypes as $contentType => $_) {
            $contentType = explode('_', $contentType, 2);
            $templateKey = GeneralUtility::underscoredToUpperCamelCase($contentType[1]);
            $templatePath = str_replace('$templateKey', $templateKey, $templateRootPath);
            $this->assertArrayHasKey($templatePath, $this->files);
        }
    }
}
