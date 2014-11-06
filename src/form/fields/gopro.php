<?php
/**
 * @package     Joomla.Platform
 * @subpackage  Form
 *
 * @copyright   Copyright (C) 2005 - 2014 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die('Restricted access');

/**
 * Form field to show an advertisement for the pro version
 */
class JFormFieldGoPro extends JFormField
{
    public $fromInstaller = false;

    protected $class = '';

    protected $media;

    protected function getInput()
    {
        $html = '';

        if (version_compare(JVERSION, '3.0', 'ge')) {
            $classJoomlaVersion = 'ost-joomla-3';
        } else {
            $classJoomlaVersion = 'ost-joomla-2';
        }

        $mediaURI  = JURI::root() . 'media/' . $this->element['media'];
        $mediaPath = JPATH_SITE . '/media/' . $this->element['media'];

        $cssPath = $mediaPath . '/css/style_gopro_field.css';
        if (file_exists($cssPath)) {
            $style = file_get_contents($cssPath);
            $html .= '<style>' . $style . '</style>';
        }

        $html .= '<div class="ost-alert-gopro ' . $this->class . ' ' . $classJoomlaVersion . ' ' . ($this->fromInstaller ? 'no_offset':'') . '">
            <a href="https://www.alledia.com/plans/" class="ost-alert-btn" target="_blank">
                <i class="icon-publish"></i> Go Pro to access more features
            </a>
            <img src="' . $mediaURI . '/images/alledia_logo.png" style="width:120px; height:auto;" alt=""/>
            ' . ($this->fromInstaller ? '<span>&copy; 2014 Alledia.com. All rights reserved.</span>':'' ) . '
        </div>';

        return $html;
    }

    protected function getLabel()
    {
        return '';
    }

    public function getInputCustomElement($element)
    {
        $this->element = $element;

        return $this->getInput();
    }
}
