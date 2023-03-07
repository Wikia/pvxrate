<?php

declare( strict_types=1 );

namespace Fandom\PvXRate;

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class SchemaHooks implements LoadExtensionSchemaUpdatesHook {
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$updater->addExtensionUpdate( [
			'addTable',
			'rating',
			__DIR__ . '/install/sql/table_rating.sql',
			true,
		] );
	}
}
