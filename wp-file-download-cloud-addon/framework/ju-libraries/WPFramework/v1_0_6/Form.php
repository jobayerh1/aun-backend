<?php
/**
 * WP Framework
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

namespace Joomunited\WPFramework\v1_0_6;

use Joomunited\WPFramework\v1_0_6\Fields;

defined('ABSPATH') || die();


/**
 * HTML5 Form class with support of xml form config files
 */
class Form
{
    /**
     * Form attributes
     *
     * @var array
     */
    public $form;

    /**
     * Final form generated content
     *
     * @var string
     */
    private $content = '';

    /**
     * Form datas
     *
     * @var array|null
     */
    private $datas = null;

    /**
     * List of errors
     *
     * @var array
     */
    private $errors = array();

    /**
     * Nonce action
     *
     * @var string
     */
    private $nonce_action = 'joomunited_save_form';

    /**
     * Nonce name
     *
     * @var string
     */
    private $nonce_name = 'joomunited_nonce_field';

    /**
     * Load an xml form file
     *
     * @param string $form  Xml file to load without path and extension
     * @param array  $datas Datas to pass to the forms
     *
     * @return boolean
     */
    public function load($form, $datas = null)
    {
        $this->datas = $datas;

        if (file_exists($form)) {
            $file = $form;
        } else {
            $form = preg_replace('/[^A-Z0-9_-]/i', '', $form);
            $file = Factory::getApplication()->getPath() . DIRECTORY_SEPARATOR . Factory::getApplication()->getType() . DIRECTORY_SEPARATOR . 'forms' . DIRECTORY_SEPARATOR . $form . '.xml';
        }

        if (!file_exists($file)) {
            return false;
        }

        $xml_content = file_get_contents($file);
        $sxi = new \SimpleXmlIterator($xml_content);
        $this->form = self::loadChildrenRecusirve($sxi);

        return true;
    }


    /**
     * Load an xml object and convert it to recursive array
     *
     * @param \SimpleXMLIterator $sxi        Iterator
     * @param boolean            $recursloop Recur
     *
     * @return mixed
     */
    protected function loadChildrenRecusirve($sxi, $recursloop = false)
    {
        $a = array();
        if ($recursloop === false) {
            $a = (array)$sxi->attributes();
        }

        // phpcs:ignore Generic.CodeAnalysis.ForLoopWithTestFunctionCall -- Keep this for readability
        for ($sxi->rewind(); $sxi->valid(); $sxi->next()) {
            $acnt = (array)$sxi->current()->attributes();
            if ($sxi->hasChildren()) {
                $acnt[] = self::loadChildrenRecusirve($sxi->current(), true);
            } else {
                $val = strval($sxi->current());
                if ($val) {
                    $acnt[] = strval($sxi->current());
                }
            }
            $a[][$sxi->key()] = $acnt;
        }
        return $a;
    }

    /**
     * Render a form to html
     *
     * @return string
     */
    public function render()
    {
        $this->content = $this->start($this->form['@attributes']);
        $this->content .= wp_nonce_field($this->nonce_action, $this->nonce_name, true, false);
        $this->content .= $this->contentRender($this->form);
        $this->content .= $this->close();
        return $this->content;
    }

    /**
     * Render the content of the form whitout the forms tags
     *
     * @param array $fields An array with the form fields
     *
     * @return string
     */
    private function contentRender($fields)
    {
        $content = '';

        foreach ($fields as $key => $value) {
            if ($key !== '@attributes') {
                $field = array_keys($value);
                if ($field[0] === 'fieldset') {
                    $content .= $this->fieldset($value);
                } else {
                    $content .= $this->input($value);
                }
            }
        }
        return $content;
    }

    /**
     * Render an input type
     *
     * @param array $input Input array with the input to render
     *
     * @return string
     */
    public function input($input)
    {
        $field = array_keys($input);

        if (!empty($input[${'field'}[0]]['@attributes']['type'])) {
            $class = ucfirst($input[${'field'}[0]]['@attributes']['type']);
        } else {
            $class = ucfirst($field[0]);
        }
        if (isset($this->datas) && !empty($input[${'field'}[0]]['@attributes']['name']) && isset($this->datas[$input[${'field'}[0]]['@attributes']['name']])) {
            $input[${'field'}[0]]['@attributes']['value'] = $this->datas[$input[${'field'}[0]]['@attributes']['name']];
        }
        if (!empty($input[${'field'}[0]]['@attributes']['namespace'])) {
            $class = $input[${'field'}[0]]['@attributes']['namespace'] . $class;
        } else {
            $class = '\Joomunited\WPFramework\v1_0_6\Fields\\' . $class;
        }
        if (class_exists($class, true)) {
            $c = new $class;
            return $c->getfield($input[$field[0]], isset($this->datas)?$this->datas:null);
        }

        return '';
    }

    /**
     * Render a fieldset
     *
     * @param array $input Array with the input to render
     *
     * @return string
     */
    public function fieldset($input)
    {
        $content = self::contentRender($input['fieldset']);
        return $this->fieldsetField($input['fieldset']['@attributes'], $content);
    }

    /**
     * Render <form> opening tag
     * Attributes are action method id class enctype
     *
     * @param array $attributes List of form attributes
     *
     * @return string
     */
    private function start(array $attributes = array())
    {
        $html = '<form';
        if (!empty($attributes)) {
            foreach ($attributes as $attribute => $value) {
                if (in_array($attribute, array('action', 'method', 'id', 'class', 'enctype')) && !empty($value)) {
                    // assign default value to 'method' attribute
                    if ($attribute === 'method' && ($value !== 'post' || $value !== 'get')) {
                        $value = 'post';
                    }
                    $html .= ' ' . $attribute . '="' . $value . '"';
                }
            }
        }
        return $html . '>';
    }

    /**
     * Render a fieldset field
     *
     * @param array  $attributes  The attributes of the fieldset
     * @param string $contentbase The content of the fieldset
     *
     * @return string
     */
    public static function fieldsetField(array $attributes, $contentbase)
    {
        $html = '<fieldset';
        $content = '';
        if (!empty($attributes)) {
            foreach ($attributes as $attribute => $value) {
                if (in_array($attribute, array('id', 'class', 'name', 'legend')) && !empty($value)) {
                    if ($attribute === 'legend') {
                        $content = '<legend>' . $value . '</legend>';
                        continue;
                    }
                    $html .= ' ' . $attribute . '="' . $value . '"';
                }
            }
        }
        return $html . '>' . $content . $contentbase . '</fieldset>';
    }


    /**
     *  Render </form> closing tag
     *
     *  @return string
     */
    public static function close()
    {
        return '</form>';
    }


    /**
     * Validate form datas
     *
     * @return boolean
     */
    public function validate()
    {
        if (empty($_REQUEST[$this->nonce_name]) || !wp_verify_nonce($_REQUEST[$this->nonce_name], $this->nonce_action)) {
            return false;
        }

        foreach ($this->form as $key => $value) {
            if ($key !== '@attributes') {
                $field = array_keys($value);
                if ($field !== 'fieldset') {
                    $field = array_keys($value);
                    if (!empty($value[${'field'}[0]]['@attributes']['type'])) {
                        $class = ucfirst($value[${'field'}[0]]['@attributes']['type']);
                    } else {
                        $class = ucfirst('Field' . $field[0]);
                    }
                    if (!empty($value[${'field'}[0]]['@attributes']['namespace'])) {
                        $class = $value[${'field'}[0]]['@attributes']['namespace'] . $class;
                    } else {
                        $class = '\Joomunited\WPFramework\v1_0_6\Fields\\' . $class;
                    }
                    if (class_exists($class)) {
                        $c = new $class;
                        if ($c->validate($value[$field[0]]['@attributes']) === false) {
                            $this->errors[] = $value[$field[0]]['@attributes']['name'];
                        }
                    }
                }
            }
        }
        if (!empty($this->errors)) {
            return false;
        }
        return true;
    }


    /**
     * Sanitize form datas
     *
     * @return array
     */
    public function sanitize()
    {
        foreach ($this->form as $key => $value) {
            if ($key !== '@attributes') {
                $field = array_keys($value);
                if (!empty($value[${'field'}[0]]['@attributes']['name'])) {
                    $field = array_keys($value);
                    if (!empty($value[${'field'}[0]]['@attributes']['type'])) {
                        $class = ucfirst($value[${'field'}[0]]['@attributes']['type']);
                    } else {
                        $class = ucfirst('Field' . $field[0]);
                    }
                    if (!empty($value[${'field'}[0]]['@attributes']['namespace'])) {
                        $class = $value[${'field'}[0]]['@attributes']['namespace'] . $class;
                    } else {
                        $class = '\Joomunited\WPFramework\v1_0_6\Fields\\' . $class;
                    }
                    if (class_exists($class)) {
                        $c = new $class;
                        $this->datas[$value[$field[0]]['@attributes']['name']] = $c->sanitize($value[$field[0]]['@attributes']);
                    }
                }
            }
        }
        return $this->datas;
    }
}
