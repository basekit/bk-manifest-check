<?php
namespace BaseKit;

use Symfony\Component\Finder\Finder;
use BaseKit\GroupNestingDereferencer;
use BaseKit\ManifestException;

/**
 * Amalgamates and verifies Json template manifest files
 *
 * Loads basekit.json and all files matching template.*.json
 * from the path passed in the constructor.
 *
 * Merges all manifest files ensuring the master basekit.json
 * manifest contains version,templates,groups and categories sections.
 *
 * Ensures there is no key duplication in the templates and categories sections.
 *
 * Ensures additional groups do not conflict based on uniqueness of the name key.
 *
 * If different versions are specified in non basekit manifest files
 * the version is appended as an additional sub-node version=blah to all non basekit
 * template sub-nodes in that manifest.
 * 
 *
 * Default path for manifest files is now: /config/template_manifests
 * 
 */
class Manifest
{
    const BASEKIT_MANIFEST = 'basekit.json';
    const PARTNER_MANIFEST_PATTERN = 'template.*.json';

    private $manifestPath;
    private $mergedManifest;
    private $manifestFilenames;
    private $manifests;
    private $groupNestingDereferencer;

    public function __construct($manifestPath)
    {
        if (!file_exists($manifestPath)) {
            throw new ManifestException('Manifest path not found');
        }
        $this->manifestPath = $manifestPath;
        if (substr($this->manifestPath, -1) != \DIRECTORY_SEPARATOR) {
            $this->manifestPath.=\DIRECTORY_SEPARATOR;
        }
        $this->groupNestingDereferencer = new GroupNestingDereferencer;
    }

    public function getTemplates()
    {
        $this->loadManifest();
        return $this->mergedManifest['templates'];
    }

    public function getGroups()
    {
        $this->loadManifest();
        return $this->mergedManifest['groups'];
    }

    public function getCategories()
    {
        $this->loadManifest();
        return $this->mergedManifest['categories'];
    }

    public function getVersion()
    {
        $this->loadManifest();
        return $this->mergedManifest['version'];
    }

    private function loadManifest()
    {
        //Lazy load the manifest on section request only
        if (null === $this->mergedManifest) {
            $this->getManifestFilesToProcess();
            $this->loadManifestFiles();
            $this->mergeManifestFiles();
            $this->prepareGroups();
        }
    }

    private function getManifestFilesToProcess()
    {
        $finder = new Finder();
        $finder->files()->in($this->manifestPath);
        foreach ($finder as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $filename = $file->getFilename();
            if ($filename == self::BASEKIT_MANIFEST || fnmatch(self::PARTNER_MANIFEST_PATTERN, $filename)) {
                $this->manifestFilenames[] = $filename;
            }
        }
        if (empty($this->manifestFilenames)) {
            throw new ManifestException(
                sprintf(
                    'No manifest files found in %s, should contain at least %s.',
                    $this->manifestPath,
                    self::BASEKIT_MANIFEST
                )
            );
        }
    }

    private function loadManifestFiles()
    {
        foreach ($this->manifestFilenames as $manifestFilename) {
            $isBaseKitManifest = ($manifestFilename == self::BASEKIT_MANIFEST);
            $manifest = json_decode(file_get_contents($this->manifestPath.$manifestFilename), true);
            if (null === $manifest) {
                throw new ManifestException(sprintf('%s is not valid JSON', $manifestFilename));
            }
            if (!isset($manifest['templates'])) {
                $this->generateMissingSectionException('templates', $manifestFilename);
            }
            if ($isBaseKitManifest) {
                if (!isset($manifest['version'])) {
                    $this->generateMissingSectionException('version', $manifestFilename);
                }
                if (!isset($manifest['groups'])) {
                    $this->generateMissingSectionException('groups', $manifestFilename);
                }
                if (!isset($manifest['categories'])) {
                    $this->generateMissingSectionException('categories', $manifestFilename);
                }
                $this->mergedManifest['version'] = $manifest['version'];
            }
            $this->manifests[] = $manifest;
        }
    }

    private function prepareGroups()
    {
        $this->addAllTemplatesGroup();
        $this->dereferenceGroupIncludes();
    }

    private function addAllTemplatesGroup()
    {
        $this->mergedManifest['groups'][] = array(
            'name' => 'All',
            'templates' => array_keys($this->getTemplates())
        );
    }

    private function dereferenceGroupIncludes()
    {
        $this->mergedManifest['groups'] = $this->groupNestingDereferencer->dereferenceGroupIncludes(
            $this->mergedManifest['groups']
        );
    }

    private function mergeManifestFiles()
    {
        foreach ($this->manifests as $manifest) {
            //The version section for non basekit manifests needs to be added
            //as a sub-node of each individual template instance
            if (isset($manifest['version']) && !empty($manifest['version'])) {
                $manifestVersion = $manifest['version'];
            } else {
                $manifestVersion = $this->mergedManifest['version'];
            }
            foreach ($manifest as $sectionName => $manifestSection) {
                if ($sectionName == 'version') {
                    continue;
                }
                $this->mergeSection($sectionName, $manifestSection, $manifestVersion);
            }
        }
    }

    private function mergeSection($sectionName, $section, $version)
    {
        if ($sectionName == 'templates' && $version != $this->mergedManifest['version']) {
            $section = $this->addTemplateVersionNodes($section, $version);
        }
        if (!isset($this->mergedManifest[$sectionName])) {
            //If this section isnt yet present in the master merged manifest array we can just add it
            $this->mergedManifest[$sectionName] = $section;
            return;
        }
        if ($sectionName == 'groups') {
            foreach ($section as $group) {
                if ($this->arraySubValueExists('name', $this->mergedManifest['groups'], $group['name'])) {
                    throw new ManifestException(sprintf('Duplicate group name %s detected', $group['name']));
                }
                $this->mergedManifest[$sectionName][] = $group;
            }
        } elseif ($sectionName == 'categories') {
            foreach ($section as $locale => $categories) {
                if (isset($this->mergedManifest[$sectionName][$locale])) {
                    $this->mergedManifest[$sectionName][$locale] = array_merge(
                        $this->mergedManifest[$sectionName][$locale],
                        $categories
                    );
                } else {
                    $this->mergedManifest[$sectionName][$locale] = $categories;
                }
            }
        } else {
            if (count(array_diff_key($section, $this->mergedManifest[$sectionName])) != count($section)) {
                throw new ManifestException(sprintf('Duplicate keys detected in section %s', $sectionName));
            }
            $this->mergedManifest[$sectionName] = $this->mergedManifest[$sectionName] + $section;
        }
    }

    private function addTemplateVersionNodes($section, $version)
    {
        foreach ($section as $templateId => $template) {
            $section[$templateId]['version'] = $version;
        }
        return $section;
    }


    /**
     * $targetArray is an array of associative arrays. This function detects if
     * $value exists as a value of the key $keyName in any of the associative arrays
     * 
     */
    private function arraySubValueExists($keyName, $targetArray, $value)
    {
        foreach ($targetArray as $subValue) {
            if ($subValue[$keyName] == $value) {
                return true;
            }
        }
        return false;
    }

    private function generateMissingSectionException($sectionName, $filename)
    {
        throw new ManifestException(sprintf('%s array not found in manifest %s', $sectionName, $filename));
    }
}
