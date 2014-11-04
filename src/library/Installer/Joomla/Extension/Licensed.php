<?php
/**
 * @package   AllediaInstaller
 * @contact   www.alledia.com, hello@alledia.com
 * @copyright 2014 Alledia.com, All rights reserved
 * @license   http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

namespace Alledia\Installer\Joomla\Extension;

defined('_JEXEC') or die();

use JFactory;
use JRegistry;

class Licensed
{
    /**
     * License type: free or pro
     *
     * @var string
     */
    protected $license;

    /**
     * Class constructor, set the extension type.
     *
     * @param string $namespace The element of the extension
     * @param string $type      The type of extension
     * @param string $folder    The folder for plugins (only)
     */
    public function __construct($namespace, $type, $folder = '', $basePath = JPATH_SITE)
    {
        parent::__construct($namespace, $type, $folder, $basePath);

        $this->license = strtolower($this->manifest->alledia->license);
    }

    /**
     * Check if the license is pro
     *
     * @return boolean True for pro license
     */
    public function isPro()
    {
        return $this->license === 'pro';
    }
}
