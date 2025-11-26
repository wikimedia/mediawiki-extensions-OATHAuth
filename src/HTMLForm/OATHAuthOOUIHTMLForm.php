<?php

namespace MediaWiki\Extension\OATHAuth\HTMLForm;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\OATHAuth\IModule;
use MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\HTMLForm\OOUIHTMLForm;
use MediaWiki\Logger\LoggerFactory;
use OOUI\FieldsetLayout;
use OOUI\HtmlSnippet;
use OOUI\Layout;
use OOUI\PanelLayout;
use OOUI\Widget;
use Psr\Log\LoggerInterface;

abstract class OATHAuthOOUIHTMLForm extends OOUIHTMLForm implements IManageForm {

	protected LoggerInterface $logger;

	protected OATHAuthModuleRegistry $moduleRegistry;

	/**
	 * @var Layout|null
	 */
	protected $layoutContainer = null;

	/**
	 * Make the form-wrapper panel padded
	 * @var bool
	 */
	protected $panelPadded = true;

	/**
	 * Make the form-wrapper panel framed
	 * @var bool
	 */
	protected $panelFramed = true;

	public function __construct(
		protected readonly OATHUser $oathUser,
		protected readonly OATHUserRepository $oathRepo,
		protected readonly IModule $module,
		IContextSource $context,
		?OATHAuthModuleRegistry $moduleRegistry = null
	) {
		$this->logger = $this->getLogger();

		parent::__construct( $this->getDescriptors(), $context, "oathauth" );

		// Temporary fallback logic so that we can update WebAuthn without causing a circular dependency
		$this->moduleRegistry = $moduleRegistry ?? OATHAuthServices::getInstance()->getModuleRegistry();
	}

	/** @inheritDoc */
	public function show( $layout = null ) {
		$this->layoutContainer = $layout;
		return parent::show();
	}

	/** @inheritDoc */
	public function displayForm( $submitResult ) {
		if ( !$this->layoutContainer instanceof Layout ) {
			parent::displayForm( $submitResult );
			return;
		}

		$this->layoutContainer->appendContent( new HtmlSnippet(
			$this->getHTML( $submitResult )
		) );
	}

	/**
	 * @return array
	 */
	protected function getDescriptors() {
		return [];
	}

	private function getLogger(): LoggerInterface {
		return LoggerFactory::getInstance( 'authentication' );
	}

	/** @inheritDoc */
	protected function wrapFieldSetSection( $legend, $section, $attributes, $isRoot ) {
		// to get a user-visible effect, wrap the fieldset into a framed panel layout
		$layout = new PanelLayout( [
			'expanded' => false,
			'infusable' => false,
			'padded' => $this->panelPadded,
			'framed' => $this->panelFramed,
		] );

		$layout->appendContent(
			new FieldsetLayout( [
				'label' => $legend,
				'infusable' => false,
				'items' => [
					new Widget( [
						'content' => new HtmlSnippet( $section )
					] ),
				],
			] + $attributes )
		);
		return $layout;
	}

	/**
	 * @param array $formData
	 * @return array|bool
	 */
	abstract public function onSubmit( array $formData );

	/**
	 * @return void
	 */
	abstract public function onSuccess();
}
