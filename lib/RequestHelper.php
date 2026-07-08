<?php
/**
 * ownCloud - files_antivirus
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Viktar Dubiniuk <dubiniuk@owncloud.com>
 *
 * @copyright Viktar Dubiniuk 2017-2018
 * @license AGPL-3.0
 */

namespace OCA\Files_Antivirus;

use OC\Cache\CappedMemoryCache;
use OC\Files\Storage\Storage;
use \OCP\IRequest;

/**
 * Used to detect the size of the uploaded file
 *
 * @package OCA\Files_Antivirus
 */
class RequestHelper {
	/**
	 * Präfix für den pfadunabhängigen Fallback-Cache-Key beim Assembly-MOVE.
	 * NUL-Byte im Präfix, damit kein realer Dateipfad kollidieren kann.
	 */
	public const MOVE_SIZE_PREFIX = "move-size\0";

	/**
	 * @var  IRequest
	 */
	private $request;

	/**
	 * @var CappedMemoryCache
	 */
	private static $fileSizeCache;

	/**
	 * RequestHelper constructor.
	 *
	 * @param IRequest $request
	 */
	public function __construct(IRequest $request) {
		$this->request = $request;
	}

	/**
	 * @return CappedMemoryCache
	 */
	public function getCache() {
		if (self::$fileSizeCache === null) {
			self::$fileSizeCache = new CappedMemoryCache();
		}
		return self::$fileSizeCache;
	}

	/**
	 * @param string $path
	 * @param string $size
	 *
	 * @return void
	 */
	public function setSizeForPath($path, $size) {
		$this->getCache()->set($path, $size);
	}

	/**
	 * Get current upload size
	 * returns null for chunks and when there is no upload
	 *
	 * @param Storage $storage
	 * @param string $path
	 *
	 * @return int|null
	 */
	public function getUploadSize(Storage $storage, $path) {
		$requestMethod = $this->request->getMethod();

		// Handle MOVE first
		// the size is cached by the app dav plugin in this case
		if ($requestMethod === 'MOVE') {
			// remove .ocTransferId444531916.part from part files
			$cleanedPath = \preg_replace(
				'|\.ocTransferId\d+\.part$|',
				'',
				$path
			);
			// cache uses dav path in /files/$user/path format
			$translatedPath = \preg_replace(
				'|^files/|',
				'files/' . $storage->getOwner('/') . '/',
				$cleanedPath
			);
			$cachedSize = $this->getCache()->get($translatedPath);
			if ($cachedSize > 0) {
				return $cachedSize;
			}
			// Bei Uploads in empfangene Shares (Jail auf der Owner-Storage) weicht
			// der interne Pfad vom Empfänger-DAV-Pfad ab, unter dem beforeMove die
			// Größe cached — Fallback über den Zieldateinamen
			$cachedSize = $this->getCache()->get(
				self::MOVE_SIZE_PREFIX . \basename($cleanedPath)
			);
			if ($cachedSize > 0) {
				return $cachedSize;
			}
		}

		// Are we uploading anything?
		if ($requestMethod !== 'PUT') {
			return null;
		}
		$isRemoteScript = $this->isScriptName('remote.php');
		$isPublicScript = $this->isScriptName('public.php');
		if (!$isRemoteScript && !$isPublicScript) {
			return null;
		}

		// v1 && v2 Chunks are not scanned
		// gilt auch für public.php: Public-Link-Web-Uploads nutzen dasselbe
		// Legacy-Chunking, gescannt wird erst die assemblierte Datei
		if (\strpos($path, 'uploads/') === 0) {
			return null;
		}

		if (\OC_FileChunking::isWebdavChunk()) {
			if (\strpos($path, 'cache/') === 0) {
				return null;
			}
			// Assembly beim finalen Legacy-Chunk: das av_max_file_size-Limit muss
			// gegen die Gesamtgröße geprüft werden, nicht gegen den letzten Chunk.
			// WICHTIG: dafür NICHT den client-kontrollierten OC-Total-Length-Header
			// verwenden — ein gelogener großer Wert würde den Scan überspringen
			// (av_max_file_size-Bypass). Stattdessen die servergezählte Summe der
			// tatsächlich gespeicherten Chunks.
			$totalLength = $this->getServerSideChunkTotal();
			if ($totalLength > 0) {
				return $totalLength;
			}
		}
		$uploadSize = (int)$this->request->getHeader('CONTENT_LENGTH');

		return $uploadSize;
	}

	/**
	 * Servergezählte Gesamtgröße des laufenden Legacy-Chunk-Transfers
	 * (Summe der bereits gespeicherten Chunks, beim Assembly = Gesamtdatei).
	 *
	 * @return int 0, wenn der Transfer nicht bestimmbar ist
	 */
	private function getServerSideChunkTotal() {
		try {
			$requestPath = \rawurldecode($this->request->getPathInfo() ?: '');
		} catch (\Exception $e) {
			$requestPath = '';
		}
		if ($requestPath === '') {
			return 0;
		}
		$info = \OC_FileChunking::decodeName(\basename($requestPath));
		if (empty($info['transferid']) || empty($info['chunkcount'])) {
			return 0;
		}
		$chunkHandler = new \OC_FileChunking($info);
		return (int)$chunkHandler->getCurrentSize();
	}

	/**
	 *
	 * @param string $string
	 *
	 * @return bool
	 */
	public function isScriptName($string) {
		$pattern = \sprintf('|/%s|', \preg_quote($string));
		return \preg_match($pattern, $this->request->getScriptName()) === 1;
	}
}
