<?php

namespace App\Libraries\H5P;

use App\H5PContent;
use App\H5PContentsMetadata;
use App\H5PLibrariesCachedAssets;
use App\H5PLibrariesHubCache;
use App\H5PLibrary;
use App\H5PLibraryLibrary;
use App\H5POption;
use App\Libraries\DataObjects\ContentStorageSettings;
use App\Libraries\H5P\Helper\H5POptionsCache;
use App\Libraries\H5P\Interfaces\CerpusStorageInterface;
use App\Libraries\H5P\Interfaces\H5PAdapterInterface;
use App\Libraries\H5P\Interfaces\Result;
use GuzzleHttp;
use GuzzleHttp\Exception\GuzzleException;
use H5PCore;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\RuntimeException;

class Framework implements \H5PFrameworkInterface, Result
{
    private $errorMessages;
    private $infoMessage;
    private $adminUrl;
    private $db;
    private $disk;

    public function __construct($db = null, Filesystem $disk)
    {
        if (get_class($db) !== 'Doctrine\DBAL\Driver\PDOConnection') {
            throw new \InvalidArgumentException(__METHOD__ . ": You must insert a PDO connection.");
        }
        $this->db = $db;
        $this->disk = $disk;
    }

    // Implements result Interface
    public function handleResult($userId, $contentId, $score, $maxScore, $opened, $finished, $time, $context)
    {
        if ($this->hasResult($userId, $contentId, $context)) {
            return $this->updateResult($userId, $contentId, $score, $maxScore, $opened, $finished, $time, $context);
        }
        return $this->insertResult($userId, $contentId, $score, $maxScore, $opened, $finished, $time, $context);
    }

    private function updateResult($userId, $contentId, $score, $maxScore, $opened, $finished, $time, $context)
    {
        $sql = "update h5p_results set score=:score, max_score=:maxScore, opened=:opened, finished=:finished, time=:time where user_id=:userId and content_id=:contentId";
        $params = [
            ':userId' => $userId,
            ':contentId' => $contentId,
            ':score' => $score,
            ':maxScore' => $maxScore,
            ':opened' => $opened,
            ':finished' => $finished,
            ':time' => $time,
        ];
        $this->getContextSql($sql, $params, $context);

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($params);
        return $result;
    }

    private function insertResult($userId, $contentId, $score, $maxScore, $opened, $finished, $time, $context)
    {
        $sql = "insert into h5p_results (user_id, content_id, score, max_score, opened, finished, time, context) values (:userId, :contentId, :score, :maxScore, :opened, :finished, :time, :context)";
        $params = [
            ':userId' => $userId,
            ':contentId' => $contentId,
            ':score' => $score,
            ':maxScore' => $maxScore,
            ':opened' => $opened,
            ':finished' => $finished,
            ':time' => $time,
            ':context' => $context
        ];

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($params);
        return $result;
    }

    private function getContextSql(&$sql, &$params, $context)
    {
        if (!is_null($context)) {
            $sql .= " and context = :context";
            $params[':context'] = $context;
        } else {
            $sql .= " and context IS NULL";
        }
    }

    private function hasResult($userId, $contentId, $context)
    {
        $sql = "select id from h5p_results where user_id=:userId and content_id=:contentId ";
        $params = [
            ':userId' => $userId,
            ':contentId' => $contentId
        ];
        $this->getContextSql($sql, $params, $context);

        $result = $this->runQuery($sql, $params);
        return !empty($result);
    }

    /**
     * Returns info for the current platform
     *
     * @return array
     *   An associative array containing:
     *   - name: The name of the plattform, for instance "Wordpress"
     *   - version: The version of the pattform, for instance "4.0"
     *   - h5pVersion: The version of the H5P plugin/module
     */
    public function getPlatformInfo()
    {
        return [
            "name" => "H5PComposer",
            "version" => "0.1",
            "h5pVersion" => "1.5"
        ];
    }

    /**
     * Fetches a file from a remote server using HTTP GET
     *
     * @param string $url Where you want to get or send data.
     * @param array $data Data to post to the URL.
     * @param bool $blocking Set to 'FALSE' to instantly time out (fire and forget).
     * @param string $stream Path to where the file should be saved.
     * @return string The content (response body). NULL if something went wrong
     */
    public function fetchExternalData($url, $data = null, $blocking = true, $stream = null)
    {
        try {
            set_time_limit(0);
            $client = new GuzzleHttp\Client();
            $method = $data ? 'POST' : 'GET';
            $options = [
                GuzzleHttp\RequestOptions::FORM_PARAMS => $data,
                GuzzleHttp\RequestOptions::TIMEOUT => !empty($blocking) ? 30 : 0.01,
            ];
            if (!empty($stream)) {
                $options[GuzzleHttp\RequestOptions::SINK] = $stream;
            }
            $response = $client->request($method, $url, $options);

            return $response->getBody()->getContents();
        } catch (GuzzleException $e) {
            Log::error(sprintf('[%s] Error: %s', __METHOD__, $e->getMessage()), ['exception' => $e]);
        }

        return null;
    }

    /**
     * Set the tutorial URL for a library. All versions of the library is set
     *
     * @param string $machineName
     * @param string $tutorialUrl
     *
     */
    public function setLibraryTutorialUrl($machineName, $tutorialUrl)
    {
        try {
            $sql = "update h5p_libraries set tutorial_url = ? where name= ?";
            $params = [$tutorialUrl, $machineName];
            $stmt = $this->db->prepare($sql);
            $res = $stmt->execute($params);
            if ($res === false) {
                throw new RuntimeException(__METHOD__ . ": Could not set tutorial url for " . $machineName);
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Show the user an error message
     *
     * @param string $message
     *   The error message
     *
     */
    public function setErrorMessage($message, $code = null)
    {
        $this->errorMessages[] = $message;
    }

    /**
     * Get error message
     *
     * @return string The error message, empty string if no message exist
     *
     */

    public function getErrorMessage($asString = true)
    {
        return $asString === true ? implode(" ", $this->errorMessages) : $this->errorMessages;
    }

    /**
     * Get error message
     *
     * @return string The error message, empty string if no message exist
     *
     */

    public function getErrorMessages()
    {
        return $this->getErrorMessage(false);
    }

    /**
     * Show the user an information message
     *
     * @param string $message
     *  The error message
     */
    public function setInfoMessage($message)
    {
        $this->infoMessage = $message;
    }

    /**
     * Get info message
     *
     * @return string The info message, empty string if no message exist
     *
     */

    public function getInfoMessage()
    {
        return $this->infoMessage;
    }

    public function getMessages($type)
    {
        return $this->infoMessage;
    }

    /**
     * Translation function
     *
     * @param string $message
     *  The english string to be translated.
     * @param type $replacements
     *   An associative array of replacements to make after translation. Incidences
     *   of any key in this array are replaced with the corresponding value. Based
     *   on the first character of the key, the value is escaped and/or themed:
     *    - !variable: inserted as is
     *    - @variable: escape plain text to HTML
     *    - %variable: escape text and theme as a placeholder for user-submitted
     *      content
     * @return string
     *   Translated string
     * TODO: Implement this for real....
     */
    public function t($message, $replacements = array())
    {
        foreach ($replacements as $key => $replacement) {
            $firstCharacter = $key[0];
            if ($firstCharacter == "!") {
                $message = str_replace($key, $replacement, $message);
            } elseif ($firstCharacter == "@" || $firstCharacter == "%") {
                $message = str_replace($key, htmlentities($replacement), $message);
            }
        }
        return $message;
    }

    public function getH5pPath()
    {
        $plugin = H5Plugin::get_instance($this->db);
        return $plugin->getPath();
    }

    /**
     * Get the Path to the last uploaded h5p
     *
     * @return string
     *   Path to the folder where the last uploaded h5p for this session is located.
     * TODO: Implement this for real....
     */
    public function getUploadedH5pFolderPath()
    {
        static $dir;

        if (is_null($dir)) {
            $dir = $this->disk->path(sprintf(ContentStorageSettings::TEMP_PATH, uniqid('h5p-')));
        }

        return $dir;
    }

    /**
     * Get the path to the last uploaded h5p file
     *
     * @return string  Path to the last uploaded h5p
     */
    public function getUploadedH5pPath()
    {
        static $path;
        if (is_null($path)) {
            $core = resolve(H5PCore::class);
            $path = $core->fs->getTmpPath() . '.h5p';
        }

        return $path;
    }

    /**
     * Get a list of the current installed libraries
     *
     * @return array
     *   Associative array containg one entry per machine name.
     *   For each machineName there is a list of libraries(with different versions)
     */
    public function loadLibraries()
    {
        return H5PLibrary::select(['id', 'name', 'title', 'major_version', 'minor_version', 'patch_version', 'runnable', 'restricted'])
            ->orderBy('major_version')
            ->orderBy('minor_version')
            ->orderBy('patch_version')
            ->getQuery()
            ->get()
            ->mapToGroups(function ($item){
                return [$item->name => $item];
            })
            ->sortBy(function ($item){
                return $item->first()->title;
            })
            ->toArray();
    }

    /**
     * Saving the unsupported library list
     *
     * @param array
     *   A list of unsupported libraries. Each list entry contains:
     *   - name: MachineName for the library
     *   - downloadUrl: URL to a location a new version of the library may be downloaded from
     *   - currentVersion: The unsupported version of the library installed on the system.
     *     This is an associative array containing:
     *     - major: The major version of the library
     *     - minor: The minor version of the library
     *     - patch: The patch version of the library
     * TODO: Check if Drupal impl has something here.
     */
    public function setUnsupportedLibraries($libraries)
    {
    }

    /**
     * Returns unsupported libraries
     *
     * @return array
     *   A list of unsupported libraries. Each entry contains an associative array with:
     *   - name: MachineName for the library
     *   - downloadUrl: URL to a location a new version of the library may be downloaded from
     *   - currentVersion: The unsupported version of the library installed on the system.
     *     This is an associative array containing:
     *     - major: The major version of the library
     *     - minor: The minor version of the library
     *     - patch: The patch version of the library
     * TODO: Check if Drupal impl has something here.
     */
    public function getUnsupportedLibraries()
    {
    }


    /**
     * Returns the URL to the library admin page
     *
     * @return string
     *   URL to admin page
     * TODO: Check if Drupal impl has something here.
     */
    public function getAdminUrl()
    {

    }

    /**
     * Set the URL to the library admin page
     *
     * @param string $message
     *   URL to admin page
     */
    public function setAdminUrl($url)
    {
        $this->adminUrl = $url;
    }

    /**
     * Get id to an existing library
     *
     * @param string $machineName
     *   The librarys machine name
     * @param int $majorVersion
     *   The librarys major version
     * @param int $minorVersion
     *   The librarys minor version
     * @return int
     *   The id of the specified library or FALSE
     */
    public function getLibraryId($machineName, $majorVersion = null, $minorVersion = null)
    {
        $library = H5PLibrary::select('id')
            ->where('name', $machineName)
            ->where('major_version', $majorVersion)
            ->where('minor_version', $minorVersion)
            ->first();

        if (!$library) {
            return false;
        }

        return (int)$library->id;
        /*
         * // The following code sometimes crashes(!!!?) when reached through an import. The Eloquent version seems to be stable.
         * // Laravel log: "Error SQLSTATE[HY000]: General error: 2006 MySQL server has gone away" (Very rude I feel...)
         * // MySQL error log: 2019-06-25T08:18:18.872848Z 332 [Note] Aborted connection 332 to db: 'content-author' user: 'root' host: 'localhost' (Got an error reading communication packets)

        $sql = "select id from h5p_libraries where name=? and major_version=? and minor_version=?";
        $statment = $this->db->prepare($sql);
        $statment->execute([$machineName, $majorVersion, $minorVersion]);
        $id = $statment->fetchColumn();
        if ($id === false) {
            return false;
        }
        return (int)$id;
        */
    }

    /**
     * Get file extension whitelist
     *
     * The default extension list is part of h5p, but admins should be allowed to modify it
     *
     * @param boolean $isLibrary
     *   TRUE if this is the whitelist for a library. FALSE if it is the whitelist
     *   for the content folder we are getting
     * @param string $defaultContentWhitelist
     *   A string of file extensions separated by whitespace
     * @param string $defaultLibraryWhitelist
     *   A string of file extensions separated by whitespace
     */
    public function getWhitelist($isLibrary, $defaultContentWhitelist, $defaultLibraryWhitelist)
    {
        // TODO: Get this value from a settings page.
        $whitelist = $defaultContentWhitelist;
        if ($isLibrary) {
            $whitelist .= ' ' . $defaultLibraryWhitelist;
        }
        $whitelist .= ' js';
        return $whitelist;
    }

    /**
     * Is the library a patched version of an existing library?
     *
     * @param object $library
     *   An associateve array containing:
     *   - machineName: The library machineName
     *   - majorVersion: The librarys majorVersion
     *   - minorVersion: The librarys minorVersion
     *   - patchVersion: The librarys patchVersion
     * @return boolean
     *   TRUE if the library is a patched version of an existing library
     *   FALSE otherwise
     * TODO: Implement this for real....
     */
    public function isPatchedLibrary($library)
    {
        return H5PLibrary::fromLibrary([
            $library['machineName'],
            $library['majorVersion'],
            $library['minorVersion']
        ])
            ->where('patch_version', "<", $library['patchVersion'])
            ->get()
            ->isNotEmpty();
    }

    /**
     * Is H5P in development mode?
     *
     * @return boolean
     *  TRUE if H5P development mode is active
     *  FALSE otherwise
     * TODO: Implement this for real....
     */
    public function isInDevMode()
    {
        return false;
    }

    /**
     * Is the current user allowed to update libraries?
     *
     * @return boolean
     *  TRUE if the user is allowed to update libraries
     *  FALSE if the user is not allowed to update libraries
     *  This is not accessible if logged out anyways
     */
    public function mayUpdateLibraries()
    {
        return \Session::get("isAdmin", false) || Request::is('admin/*') || Request::is("api/v1/h5p/import");
    }

    /**
     * Store data about a library
     *
     * Also fills in the libraryId in the libraryData object if the object is new
     *
     * @param array $libraryData
     *   Associative array containing:
     *   - libraryId: The id of the library if it is an existing library.
     *   - title: The library's name
     *   - machineName: The library machineName
     *   - majorVersion: The library's majorVersion
     *   - minorVersion: The library's minorVersion
     *   - patchVersion: The library's patchVersion
     *   - runnable: 1 if the library is a content type, 0 otherwise
     *   - fullscreen(optional): 1 if the library supports fullscreen, 0 otherwise
     *   - embedTypes(optional): list of supported embed types
     *   - preloadedJs(optional): list of associative arrays containing:
     *     - path: path to a js file relative to the library root folder
     *   - preloadedCss(optional): list of associative arrays containing:
     *     - path: path to css file relative to the library root folder
     *   - dropLibraryCss(optional): list of associative arrays containing:
     *     - machineName: machine name for the librarys that are to drop their css
     *   - semantics(optional): Json describing the content structure for the library
     *   - language(optional): associative array containing:
     *     - languageCode: Translation in json format
     * TODO: Implement this for real....
     */
    public function saveLibraryData(&$library, $new = true)
    {
        $preloadedJs = $this->pathsToCsv($library, 'preloadedJs', 'path');
        $preloadedCss = $this->pathsToCsv($library, 'preloadedCss', 'path');
        $dropLibraryCss = $this->pathsToCsv($library, 'dropLibraryCss', 'machineName');

        $embedTypes = '';
        if (isset($library['embedTypes'])) {
            $embedTypes = implode(', ', $library['embedTypes']);
        }
        if (!isset($library['semantics'])) {
            $library['semantics'] = '';
        }
        if (!isset($library['fullscreen'])) {
            $library['fullscreen'] = 0;
        }

        $library['metadataSettings'] = isset($library['metadataSettings']) ? \H5PMetadata::boolifyAndEncodeSettings($library['metadataSettings']) : null;
        $library['addTo'] = isset($library['addTo']) ? json_encode($library['addTo']) : null;

        /** @var H5PLibrary $h5pLibrary */
        $h5pLibrary = H5PLibrary::updateOrCreate([
            'id' => !$new ? $library['libraryId'] : null
        ], [
            'name' => $library['machineName'],
            'title' => $library['title'],
            'major_version' => $library['majorVersion'],
            'minor_version' => $library['minorVersion'],
            'patch_version' => $library['patchVersion'],
            'runnable' => $library['runnable'],
            'fullscreen' => $library['fullscreen'],
            'embed_types' => $embedTypes,
            'preloaded_js' => $preloadedJs,
            'preloaded_css' => $preloadedCss,
            'drop_library_css' => $dropLibraryCss,
            'semantics' => $library['semantics'],
            'metadata_settings' => $library['metadataSettings'],
            'add_to' => $library['addTo'],
            'has_icon' => $library['hasIcon'] ?? 0,
            'tutorial_url' => ''
        ]);
        $library['libraryId'] = $h5pLibrary->id;

        $h5pLibrary->libraries()->delete();
        $h5pLibrary->languages()->delete();
        if (isset($library['language'])) {
            foreach ($library['language'] as $languageCode => $translation) {
                $h5pLibrary->languages()->create([
                    'library_id' => $library['libraryId'],
                    'language_code' => $languageCode,
                    'translation' => $translation
                ]);
            }
        }
    }

    /**
     * Insert new content.
     *
     * @param array $content
     *   An associative array containing:
     *   - id: The content id
     *   - user_id: The users ID
     *   - title: Title
     *   - params: The content in json format
     *   - library: An associative array containing:
     *     - libraryId: The id of the main library for this content
     * @param int $contentMainId
     *   Main id for the content if this is a system that supports versioning
     */
    public function insertContent($content, $contentMainId = null)
    {
        try {
            $adapter = app(H5PAdapterInterface::class);
            $metadataRaw = (array)$content['metadata'] ?? [];
            $metadata = \H5PMetadata::toDBArray($metadataRaw, true);

            $H5PContent = H5PContent::make();
            $H5PContent->title = !empty($metadata['title']) ? $metadata['title'] : $content['title'];
            $H5PContent->parameters = $content['params'];
            $H5PContent->filtered = '';
            $H5PContent->library_id = $content['library']['libraryId'];
            $H5PContent->embed_type = $content['embed_type'];
            $H5PContent->disable = $content['disable'];
            $H5PContent->max_score = !is_null($content['max_score']) ? (int)$content['max_score'] : null;
            $H5PContent->slug = !empty($content['slug']) ? $content['slug'] : '';
            $H5PContent->user_id = $content['user_id'];
            $H5PContent->content_create_mode = $adapter->getAdapterName();
            $H5PContent->is_published = $content['is_published'] ?? !$adapter->enableDraftLogic();
            $H5PContent->is_private =  $content['is_private'] ?? 1;
            $H5PContent->language_iso_639_3 = $content['language_iso_639_3'] ?? null;

            $H5PContent->save();
            unset($metadata['title']);

            if (!empty($metadata)) {
                $metadata['content_id'] = $H5PContent->id;
                /** @var H5PContentsMetadata $H5PContentMetadata */
                $H5PContentMetadata = H5PContentsMetadata::make($metadata);
                $H5PContentMetadata->save();
            }

            return $H5PContent->id;
        } catch (Exception $e) {
            throw $e;
        }
    }


    /**
     * Update old content.
     *
     * @param array $content
     *   An associative array containing:
     *   - id: The content id
     *   - params: The content in json format
     *   - library: An associative array containing:
     *     - libraryId: The id of the main library for this content
     * @param int $contentMainId
     *   Main id for the content if this is a system that supports versioning
     * TODO: Implement this for real....
     */
    public function updateContent($content, $contentMainId = null)
    {
        try {
            $metadataRaw = (array)$content['metadata'];
            $metadata = \H5PMetadata::toDBArray($metadataRaw, true);

            $H5PContent = H5PContent::find($content['id']);
            $H5PContent->title = !empty($metadata['title']) ? $metadata['title'] : $content['title'];
            $H5PContent->parameters = $content['params'];
            $H5PContent->filtered = '';
            $H5PContent->library_id = $content['library']['libraryId'];
            $H5PContent->embed_type = $content['embed_type'];
            $H5PContent->disable = $content['disable'];
            $H5PContent->slug = $content['slug'];
            $H5PContent->max_score = (int)$content['max_score'];
            $H5PContent->is_published = $content['is_published'];
            $H5PContent->language_iso_639_3 = $content['language_iso_639_3'] ?? null;

            $H5PContent->update();
            unset($metadata['title']);

            if (!empty($metadata)) {
                /** @var H5PContentsMetadata $H5PContentMetadata */
                $H5PContentMetadata = H5PContentsMetadata::firstOrNew([
                    'content_id' => $H5PContent->id
                ]);
                $H5PContentMetadata->fill($metadata);
                $H5PContentMetadata->save();
            }

            return $H5PContent;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Resets marked user data for the given content.
     *
     * @param int $contentId
     * TODO: Implement this for real....
     */
    public function resetContentUserData($contentId)
    {
        return true;
    }

    /**
     * Save what libraries a library is dependending on
     *
     * @param int $libraryId
     *   Library Id for the library we're saving dependencies for
     * @param array $dependencies
     *   List of dependencies as associative arrays containing:
     *   - machineName: The library machineName
     *   - majorVersion: The library's majorVersion
     *   - minorVersion: The library's minorVersion
     * @param string $dependency_type
     *   What type of dependency this is, the following values are allowed:
     *   - editor
     *   - preloaded
     *   - dynamic
     * TODO: Implement this for real....
     */
    public function saveLibraryDependencies($library_id, $dependencies, $dependency_type)
    {
        foreach ($dependencies as $dependency) {
            $libraries = H5PLibrary::fromLibrary([$dependency['machineName'],$dependency['majorVersion'],$dependency['minorVersion']])
                ->select('id')
                ->get()
                ->each(function ($library) use ($library_id, $dependency_type){
                    H5PLibraryLibrary::updateOrCreate([
                        'library_id' => $library_id,
                        'required_library_id' => $library['id'],
                        'dependency_type' => $dependency_type
                    ],
                    [
                        'dependency_type' => $dependency_type
                    ]);
                });
        }
    }

    /**
     * Give an H5P the same library dependencies as a given H5P
     *
     * @param int $contentId
     *   Id identifying the content
     * @param int $copyFromId
     *   Id identifying the content to be copied
     * @param int $contentMainId
     *   Main id for the content, typically used in frameworks
     *   That supports versioning. (In this case the content id will typically be
     *   the version id, and the contentMainId will be the frameworks content id
     */
    public function copyLibraryUsage($contentId, $copyFromId, $contentMainId = null)
    {
        $sql = "INSERT INTO h5p_contents_libraries (content_id, library_id, dependency_type, weight, drop_css)
        SELECT ?, hcl.library_id, hcl.dependency_type, hcl.weight, hcl.drop_css
          FROM h5p_contents_libraries hcl
          WHERE hcl.content_id = ?";

        $this->db->prepare($sql)->execute([$contentId, $copyFromId]);
    }

    /**
     * Deletes content data
     *
     * @param int $contentId
     *   Id identifying the content
     */
    public function deleteContentData($contentId)
    {
        $this->runQuery("delete from h5p_contents where id=?", [$contentId]);
        $this->runQuery("delete from h5p_results where content_id=?", [$contentId]);
        $this->runQuery("delete from h5p_contents_user_data where content_id=?", [$contentId]);
    }

    /**
     * Delete what libraries a content item is using
     *
     * @param int $contentId
     *   Content Id of the content we'll be deleting library usage for
     */
    public function deleteLibraryUsage($contentId)
    {
        $sql = "delete from h5p_contents_libraries where content_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$contentId]);
    }

    /**
     * Convert list of file paths to csv
     *
     * @param array $library
     *  Library data as found in library.json files
     * @param string $key
     *  Key that should be found in $libraryData
     * @param $pluck
     *  Value to pluck
     * @return string
     *  file paths separated by ', '
     */
    private function pathsToCsv($library, $key, $pluck)
    {
        return collect($library[$key] ?? [])->pluck($pluck)->implode(", ");
    }

    /**
     * Saves what libraries the content uses
     *
     * @param int $contentId
     *   Id identifying the content
     * @param array $librariesInUse
     *   List of libraries the content uses. Libraries consist of associative arrays with:
     *   - library: Associative array containing:
     *     - dropLibraryCss(optional): commasepareted list of machineNames
     *     - machineName: Machine name for the library
     *     - libraryId: Id of the library
     *   - type: The dependency type. Allowed values:
     *     - editor
     *     - dynamic
     *     - preloaded
     */
    public function saveLibraryUsage($contentId, $librariesInUse)
    {
        $dropLibraryCssList = array();

        foreach ($librariesInUse as $dependency) {
            if (!empty($dependency['library']['dropLibraryCss'])) {
                $dropLibraryCssList = array_merge($dropLibraryCssList,
                    explode(', ', $dependency['library']['dropLibraryCss']));
            }
        }

        $dependencySQL = "insert into  h5p_contents_libraries values ( :content_id, :library_id, :dependency_type, :weight, :drop_css) ";
        $dependencyStmt = $this->db->prepare($dependencySQL);
        foreach ($librariesInUse as $dependency) {
            $dropCss = in_array($dependency['library']['machineName'], $dropLibraryCssList) ? 1 : 0;
            $params = [
                ':content_id' => $contentId,
                ':library_id' => $dependency['library']['libraryId'],
                ':dependency_type' => $dependency['type'],
                ':weight' => $dependency['weight'],
                ':drop_css' => $dropCss
            ];
            $dependencyStmt->execute($params);
        }
    }

    /**
     * Get number of content/nodes using a library, and the number of
     * dependencies to other libraries
     *
     * @param int $libraryId
     *   Library identifier
     * @return array
     *   Associative array containing:
     *   - content: Number of content using the library
     *   - libraries: Number of libraries depending on the library
     */
    public function getLibraryUsage($libraryId, $skipContent = false)
    {
        $usage = [
            'libraries' => H5PLibraryLibrary::where('required_library_id', $libraryId)->count(),
            'content' => null,
        ];
        if ($skipContent === false) {
            $usage['content'] = H5PContent::where('library_id', $libraryId)->count();
        }
        return $usage;
    }

    /**
     * Loads a library
     *
     * @param string $machineName
     *   The library's machine name
     * @param int $majorVersion
     *   The library's major version
     * @param int $minorVersion
     *   The library's minor version
     * @return array|FALSE
     *   FALSE if the library doesn't exist.
     *   Otherwise an associative array containing:
     *   - libraryId: The id of the library if it is an existing library.
     *   - title: The library's name
     *   - machineName: The library machineName
     *   - majorVersion: The library's majorVersion
     *   - minorVersion: The library's minorVersion
     *   - patchVersion: The library's patchVersion
     *   - runnable: 1 if the library is a content type, 0 otherwise
     *   - fullscreen(optional): 1 if the library supports fullscreen, 0 otherwise
     *   - embedTypes(optional): list of supported embed types
     *   - preloadedJs(optional): comma separated string with js file paths
     *   - preloadedCss(optional): comma separated sting with css file paths
     *   - dropLibraryCss(optional): list of associative arrays containing:
     *     - machineName: machine name for the librarys that are to drop their css
     *   - semantics(optional): Json describing the content structure for the library
     *   - preloadedDependencies(optional): list of associative arrays containing:
     *     - machineName: Machine name for a library this library is depending on
     *     - majorVersion: Major version for a library this library is depending on
     *     - minorVersion: Minor for a library this library is depending on
     *   - dynamicDependencies(optional): list of associative arrays containing:
     *     - machineName: Machine name for a library this library is depending on
     *     - majorVersion: Major version for a library this library is depending on
     *     - minorVersion: Minor for a library this library is depending on
     *   - editorDependencies(optional): list of associative arrays containing:
     *     - machineName: Machine name for a library this library is depending on
     *     - majorVersion: Major version for a library this library is depending on
     *     - minorVersion: Minor for a library this library is depending on
     * TODO: handle dependencies too...
     */
    public function loadLibrary($machineName, $majorVersion, $minorVersion)
    {
        $sql = "select
            id as libraryId,
            title as title,
            name as machineName,
            major_version as majorVersion,
            minor_version as minorVersion,
            patch_version as patchVersion,
            runnable,
            fullscreen,
            embed_types as embedTypes,
            preloaded_js as preloadedJs,
            preloaded_css as preloadedCss,
            drop_library_css as dropLibraryCss,
            semantics
        from h5p_libraries
        where
            name = ?
            and major_version = ?
            and minor_version = ?";

        $libraryStatement = $this->db->prepare($sql);
        $libraryStatement->execute([$machineName, $majorVersion, $minorVersion]);
        $library = $libraryStatement->fetch(\PDO::FETCH_ASSOC);

        $dependenciesStatement = $this->db->prepare(
            "SELECT hl.name as machineName, hl.major_version as majorVersion, hl.minor_version as minorVersion, hll.dependency_type as dependencyType
        FROM h5p_libraries_libraries hll
        JOIN h5p_libraries hl ON hll.required_library_id = hl.id
        WHERE hll.library_id = ?");
        $dependenciesStatement->execute([$library['libraryId']]);
        $dependencies = $dependenciesStatement->fetchAll(\PDO::FETCH_OBJ);
        foreach ($dependencies as $dependency) {
            $library[$dependency->dependencyType . 'Dependencies'][] = array(
                'machineName' => $dependency->machineName,
                'majorVersion' => $dependency->majorVersion,
                'minorVersion' => $dependency->minorVersion,
            );
        }

        return $library;
    }

    public function loadLibraryInfo($id)
    {
        $sql = "select
            id as libraryId,
            title as title,
            name as machineName,
            major_version as majorVersion,
            minor_version as minorVersion,
            patch_version as patchVersion,
            runnable,
            fullscreen,
            embed_types as embedTypes,
            preloaded_js as preloadedJs,
            preloaded_css as preloadedCss,
            drop_library_css as dropLibraryCss,
            semantics
        from h5p_libraries
        where
            id = :id";

        $libraryStatement = $this->db->prepare($sql);
        $libraryStatement->execute([':id' => $id]);
        $library = $libraryStatement->fetch(\PDO::FETCH_ASSOC);
        return $library;

    }

    /**
     * Loads library semantics.
     *
     * @param string $machineName
     *   Machine name for the library
     * @param int $majorVersion
     *   The library's major version
     * @param int $minorVersion
     *   The library's minor version
     * @return string
     *   The library's semantics as json
     */
    public function loadLibrarySemantics($machineName, $majorVersion, $minorVersion)
    {
        $sql = "select semantics from h5p_libraries
          WHERE
            name=?
            and major_version = ?
            and minor_version = ?";
        $statement = $this->db->prepare($sql);
        $statement->execute([$machineName, $majorVersion, $minorVersion]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row['semantics'];
    }

    /**
     * Makes it possible to alter the semantics, adding custom fields, etc.
     *
     * @param array $semantics
     *   Associative array representing the semantics
     * @param string $machineName
     *   The library's machine name
     * @param int $majorVersion
     *   The library's major version
     * @param int $minorVersion
     *   The library's minor version
     */
    public function alterLibrarySemantics(&$semantics, $machineName, $majorVersion, $minorVersion)
    {
        $adapter = app(H5PAdapterInterface::class);
        $adapter->alterLibrarySemantics($semantics, $machineName, $majorVersion, $minorVersion);
    }

    /**
     * Delete all dependencies belonging to given library
     *
     * @param int $libraryId
     *   Library identifier
     */
    public function deleteLibraryDependencies($libraryId)
    {
        H5PLibraryLibrary::where('library_id', $libraryId)->delete();
    }

    /**
     * Start an atomic operation against the dependency storage
     * TODO: Implement this for real
     * TODO: Check Drupal source for what is supposed to happen, WP does not support this.
     */
    public function lockDependencyStorage()
    {
    }

    /**
     * Stops an atomic operation against the dependency storage
     * TODO: Implement this for real....
     * TODO: Check Drupal source for what is supposed to happen, WP does not support this.
     */
    public function unlockDependencyStorage()
    {
    }


    /**
     * Delete a library from database and file system
     *
     * @param stdClass $library
     *   Library object with id, name, major version and minor version.
     */
    public function deleteLibrary($library)
    {
        $success = false;
        if (isset($library) && is_object($library)) {
            /** @var H5PLibrary $library */
            $success = $library->delete();
            if ($success) {
                $core = resolve(H5PCore::class);
                $success = $core->fs->deleteLibrary($library);
            }
        }
        return [
            'success' => $success,
        ];
    }

    /**
     * Load content.
     *
     * @param int $id
     *   Content identifier
     * @return array
     *   Associative array containing:
     *   - contentId: Identifier for the content
     *   - params: json content as string
     *   - embedType: csv of embed types
     *   - title: The contents title
     *   - language: Language code for the content
     *   - libraryId: Id for the main library
     *   - libraryName: The library machine name
     *   - libraryMajorVersion: The library's majorVersion
     *   - libraryMinorVersion: The library's minorVersion
     *   - libraryEmbedTypes: CSV of the main library's embed types
     *   - libraryFullscreen: 1 if fullscreen is supported. 0 otherwise.
     * TODO: Handle language
     */
    public function loadContent($id)
    {
        /** @var H5PContent $h5pcontent */
        $h5pcontent = H5PContent::with(['library', 'metadata'])
            ->findOrFail($id);

        $content = [
            'id' => $h5pcontent->id,
            'contentId' => $h5pcontent->id,
            'params' => $h5pcontent->parameters,
            'filtered' => $h5pcontent->filtered,
            'embedType' => $h5pcontent->embed_type,
            'title' => $h5pcontent->title,
            'disable' => $h5pcontent->disable,
            'user_id' => $h5pcontent->user_id,
            'slug' => $h5pcontent->slug,
            'libraryId' => $h5pcontent->library->id,
            'libraryName' => $h5pcontent->library->name,
            'libraryMajorVersion' => $h5pcontent->library->major_version,
            'libraryMinorVersion' => $h5pcontent->library->minor_version,
            'libraryEmbedTypes' => $h5pcontent->library->embed_types,
            'libraryFullscreen' => $h5pcontent->library->fullscreen,
            'language' => $h5pcontent->metadata->default_language ?? null,
            'max_score' => $h5pcontent->max_score,
            'created_at' => $h5pcontent->created_at,
            'updated_at' => $h5pcontent->updated_at,
        ];

        $content['metadata'] = $h5pcontent->getMetadataStructure();

        return $content;
    }

    /**
     * Load dependencies for the given content of the given type.
     *
     * @param int $id
     *   Content identifier
     * @param int $type
     *   Dependency types. Allowed values:
     *   - editor
     *   - preloaded
     *   - dynamic
     * @return array
     *   List of associative arrays containing:
     *   - libraryId: The id of the library if it is an existing library.
     *   - machineName: The library machineName
     *   - majorVersion: The library's majorVersion
     *   - minorVersion: The library's minorVersion
     *   - patchVersion: The library's patchVersion
     *   - preloadedJs(optional): comma separated string with js file paths
     *   - preloadedCss(optional): comma separated sting with css file paths
     *   - dropCss(optional): csv of machine names
     */
    public function loadContentDependencies($id, $type = null)
    {
        $allowedDependencyTypes = ['editor', 'preloaded', 'dynamic'];
        if ($type !== null && !in_array($type, $allowedDependencyTypes)) {
            throw new RuntimeException(__METHOD__ . ": invalid dependency type. Only editor, preloaded or dynamic is allowed");
        }
        $sql =
            "SELECT hl.id
              , hl.name AS machineName
              , hl.major_version AS majorVersion
              , hl.minor_version AS minorVersion
              , hl.patch_version AS patchVersion
              , hl.preloaded_css AS preloadedCss
              , hl.preloaded_js AS preloadedJs
              , hcl.drop_css AS dropCss
              , hcl.dependency_type AS dependencyType
        FROM h5p_contents_libraries hcl
        JOIN h5p_libraries hl ON hcl.library_id = hl.id
        WHERE hcl.content_id = ?";
        $queryArgs = [$id];

        if ($type !== null) {
            $sql .= " AND hcl.dependency_type = ?";
            $queryArgs[] = $type;
        }

        $sql .= " ORDER BY hcl.weight";

        $cstmt = $this->db->prepare($sql);
        $cstmt->execute($queryArgs);
        $content = $cstmt->fetchAll(\PDO::FETCH_ASSOC);
        return $content;
    }

    /**
     * Get stored setting.
     *
     * @param string $name
     *   Identifier for the setting
     * @param string $default
     *   Optional default value if settings is not set
     * @return mixed
     *   Whatever has been stored as the setting
     */
    public function getOption($name, $default = null)
    {
        switch ($name) {
            case "export":
                return config('h5p.defaultExportOption');
            case "embed":
                /** @var H5PAdapterInterface $adapter */
                $adapter = app(H5PAdapterInterface::class);
                return $adapter->useEmbedLink();
            case 'enable_lrs_content_types':
                return true;
            case 'send_usage_statistics':
                return false;
            case 'hub_is_enabled':
                return config('h5p.isHubEnabled') || Request::is('admin/*');
            default:
                return app(H5POptionsCache::class)->get($name, $default);
        }
    }


    /**
     * Stores the given setting.
     * For example when did we last check h5p.org for updates to our libraries.
     *
     * @param string $name
     *   Identifier for the setting
     * @param mixed $value Data
     *   Whatever we want to store as the setting
     */
    public function setOption($name, $value)
    {
        H5POption::updateOrCreate(['option_name' => $name], ['option_value' => $value]);
    }

    /**
     * This will update selected fields on the given content.
     *
     * @param int $id Content identifier
     * @param array $fields Content fields, e.g. filtered or slug.
     * TODO: Implement this for real....
     */
    public function updateContentFields($id, $fields)
    {
        /** @var H5PContent $content */
        $content = H5PContent::findOrFail($id);
        $content->fill($fields);
        if ($content->isDirty([
            'filtered',
            'slug'
        ])) {
            return $content->save();
        }
        return true;
    }

    /**
     * Will clear filtered params for all the content that uses the specified
     * libraries. This means that the content dependencies will have to be rebuilt,
     * and the parameters refiltered.
     *
     * @param array $library_ids
     */
    public function clearFilteredParameters($library_ids)
    {
        if (!is_array($library_ids)) {
            $library_ids = [$library_ids];
        }
        H5PContent::whereIn('library_id', $library_ids)->update(['filtered' => '']);
    }

    /**
     * Get number of contents that has to get their content dependencies rebuilt
     * and parameters refiltered.
     *
     * @return int
     */
    public function getNumNotFiltered()
    {
        // Needs to be looked at. When the numper of H5Ps increase this query takes too long
        return H5PContent::where('filtered', '')->count();
    }

    /**
     * Get number of contents using library as main library.
     *
     * @param int $libraryId
     * @param array $skip
     * @return int
     */
    public function getNumContent($libraryId, $skip = null)
    {
        return H5PContent::where('library_id', $libraryId)->count();
    }

    /**
     * Determines if content slug is used.
     *
     * @param string $slug
     * @return boolean
     */
    public function isContentSlugAvailable($slug)
    {
        $sql = "select slug from h5p_contents where slug=?";
        $res = $this->db->prepare($sql)->execute([$slug])->fetch(\PDO::FETCH_ASSOC);
        if (sizeof($res) > 0) {
            return false;
        }
        return true;
    }

    /**
     * Cerpus functions
     */

    /**
     * @param $sql
     * @param array $params
     * @param bool $returnFirst
     * @return mixed
     */
    private function runQuery($sql, $params = [], $returnFirst = false)
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        if ($returnFirst === true) {
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        }
        $all = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $all;
    }

    public function getLibraryStats($type)
    {
        // TODO: implement this
        return [];
    }

    public function getNumAuthors()
    {
        // TODO: Implement getNumAuthors() method.
    }

    public function saveCachedAssets($key, $libraries)
    {
        foreach ($libraries as $library){
            H5PLibrariesCachedAssets::create([
                'hash' => $key,
                'library_id' => $library['id']
            ]);
        }
    }

    public function deleteCachedAssets($library_id)
    {
        $cachedAssets = H5PLibrariesCachedAssets::where('library_id', $library_id)->get();
        $cachedAssets->each(function ($asset){
            $asset->delete();
        });
        return $cachedAssets->pluck('hash')->toArray();
    }


    /**
     * Get the amount of content items associated to a library
     * @return array
     */
    public function getLibraryContentCount()
    {
        $libraries = H5PLibrary::all()
            ->filter(function ($library) {
                return $library->runnable != "0" && $library->contents()->count() > 0;
            })
            ->transform(function ($library) {
                $item = new \stdClass();
                $item->key = sprintf("%s %s.%s", $library->name, $library->major_version,
                    $library->minor_version);
                $item->count = $library->contents()->count();

                return $item;
            });

        $libraryCount = [];
        foreach ($libraries as $library) {
            $libraryCount[$library->key] = $library->count;
        }

        return $libraryCount;
    }

    /**
     * Will trigger after the export file is created.
     */
    public function afterExportCreated($content, $filename)
    {
        // TODO: Implement afterExportCreated() method.
    }

    public function hasPermission($permission, $id = null)
    {
        switch ($permission) {
            case \H5PPermission::DOWNLOAD_H5P:
            case \H5PPermission::EMBED_H5P:
                return false;

            case \H5PPermission::CREATE_RESTRICTED:
                return false;

            case \H5PPermission::INSTALL_RECOMMENDED:
            case \H5PPermission::UPDATE_LIBRARIES:
                return Request::is('admin/*');
        }
    }

    /**
     * Get URL to file in the specific library
     * @param string $libraryFolderName
     * @param string $fileName
     * @return string URL to file
     */
    public function getLibraryFileUrl($libraryFolderName, $fileName)
    {
        $storageInterface = app(CerpusStorageInterface::class);

        $path = implode("/", [
            'libraries',
            $libraryFolderName,
            $fileName
        ]);

        return $storageInterface->getFileUrl($path);
    }

    /**
     * Replaces existing content type cache with the one passed in
     *
     * @param object $contentTypeCache Json with an array called 'libraries'
     *  containing the new content type cache that should replace the old one.
     */
    public function replaceContentTypeCache($contentTypeCache)
    {
        DB::transaction(function () use ($contentTypeCache) {
            H5PLibrariesHubCache::where('owner', '<>', 'Cerpus')->delete();

            foreach ($contentTypeCache->contentTypes as $type) {
                H5PLibrariesHubCache::create([
                    'name' => $type->id,
                    'major_version' => $type->version->major,
                    'minor_version' => $type->version->minor,
                    'patch_version' => $type->version->patch,
                    'h5P_major_version' => $type->coreApiVersionNeeded->major,
                    'h5P_minor_version' => $type->coreApiVersionNeeded->minor,
                    'title' => $type->title,
                    'summary' => $type->summary,
                    'description' => $type->description,
                    'icon' => $type->icon,
                    'is_recommended' => $type->isRecommended,
                    'popularity' => $type->popularity,
                    'screenshots' => !empty($type->screenshots) ? json_encode($type->screenshots) : '',
                    'license' => json_encode($type->license),
                    'example' => $type->example ?? '',
                    'tutorial' => $type->tutorial ?? '',
                    'keywords' => !empty($type->keywords) ? json_encode($type->keywords) : '',
                    'categories' => json_encode($type->categories ?? []),
                    'owner' => $type->owner,
                ]);
            }
        });
    }

    /**
     * Load addon libraries
     *
     * @return array
     */
    public function loadAddons()
    {
        return H5PLibrary::make()->getAddons();
    }

    /**
     * Load config for libraries
     *
     * @param array $libraries
     * @return array
     */
    public function getLibraryConfig($libraries = null)
    {
        return [];
    }

    /**
     * Checks if the given library has a higher version.
     *
     * @param array $library
     * @return boolean
     */
    public function libraryHasUpgrade($library)
    {
        $h5pLibrary = H5PLibrary::fromLibrary($library)->first();
        return !is_null($h5pLibrary) && $h5pLibrary->isUpgradable();
    }
}

