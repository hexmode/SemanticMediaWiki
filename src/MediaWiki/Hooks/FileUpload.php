<?php

namespace SMW\MediaWiki\Hooks;

use File;
use Hooks;
use MediaWiki\MediaWikiServices;
use ParserOptions;
use SMW\Services\ServicesFactory as ApplicationFactory;
use SMW\Localizer;
use SMW\NamespaceExaminer;
use Title;
use User;
use SMW\MediaWiki\HookListener;

/**
 * Fires when a local file upload occurs
 *
 * @see https://www.mediawiki.org/wiki/Manual:Hooks/FileUpload
 *
 * @license GNU GPL v2+
 * @since 1.9
 *
 * @author mwjames
 */
class FileUpload implements HookListener {

	/**
	 * @var NamespaceExaminer
	 */
	private $namespaceExaminer;

	/**
	 * @since 1.9
	 *
	 * @param NamespaceExaminer $namespaceExaminer
	 */
	public function __construct( NamespaceExaminer $namespaceExaminer ) {
		$this->namespaceExaminer = $namespaceExaminer;
	}

	/**
	 * @since 3.0
	 *
	 * @param File $file
	 * @param boolean $reUploadStatus
	 *
	 * @return true
	 */
	public function process( File $file, $reUploadStatus = false ) {

		if ( $this->canProcess( $file->getTitle() ) ) {
			$this->doProcess( $file, $reUploadStatus );
		}

		return true;
	}

	private function canProcess( $title ) {
		return $title !== null && $this->namespaceExaminer->isSemanticEnabled( $title->getNamespace() );
	}

	private function doProcess( File $file, bool $reUploadStatus = false ): void {

		$filePage = $this->makeFilePage( $file, $reUploadStatus );

		// Avoid WikiPage.php exception: The supplied ParserOptions are not safe to cache.
		// Fix the options or set $forceParse = true.
		$forceParse = true;

		$applicationFactory = ApplicationFactory::getInstance();
		$mws = MediaWikiServices::getInstance();
		if ( method_exists( $mws, "getParserOutputAccess" ) ) {
			$revision = $filePage->getRevisionRecord();
			throw new \Exception( var_export( $revision, true ) );
			$status = MediaWikiServices::getInstance()->getParserOutputAccess()->getParserOutput(
				$filePage, $this->makeCanonicalParserOptions(), $revision, $forceParse
			);
			if ( !$status->isOK() ) {
				// Use __toString to aid debugging
				throw new \Exception( "Got error during FileUpload hook.\n" . $status );
			}
			$parserData = $applicationFactory->newParserData( $file->getTitle(), $status->getValue() );
		} else {
			$parserData = $applicationFactory->newParserData(
				$file->getTitle(),
				$filePage->getParserOutput( $this->makeCanonicalParserOptions(), null, $forceParse )
			);
		}

		$pageInfoProvider = $applicationFactory->newMwCollaboratorFactory()->newPageInfoProvider(
			$filePage
		);

		$propertyAnnotatorFactory = $applicationFactory->singleton( 'PropertyAnnotatorFactory' );

		$semanticData = $parserData->getSemanticData();
		$semanticData->setOption( 'is_fileupload', true );

		$propertyAnnotator = $propertyAnnotatorFactory->newNullPropertyAnnotator(
			$semanticData
		);

		$propertyAnnotator = $propertyAnnotatorFactory->newPredefinedPropertyAnnotator(
			$propertyAnnotator,
			$pageInfoProvider
		);

		$propertyAnnotator->addAnnotation();

		// 2.4+
		Hooks::run( 'SMW::FileUpload::BeforeUpdate', [ $filePage, $semanticData ] );

		$parserData->setOrigin( 'FileUpload' );

		$parserData->pushSemanticDataToParserOutput();
		$parserData->updateStore( true );
	}

	private function makeFilePage( $file, $reUploadStatus ) {

		$filePage = ApplicationFactory::getInstance()->newPageCreator()->createFilePage(
			$file->getTitle()
		);

		$filePage->setFile( $file );
		$filePage->smwFileReUploadStatus = $reUploadStatus;

		return $filePage;
	}

	/**
	 * Anonymous user with default preferences and content language
	 */
	private function makeCanonicalParserOptions() {
		return ParserOptions::newFromUserAndLang(
			new User(),
			Localizer::getInstance()->getContentLanguage()
		);
	}

}
