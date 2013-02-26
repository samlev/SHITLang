<?php

namespace Tpl;

class Engine {
    const DUPLICATES_ERROR = 0;
    const DUPLICATES_OVERWRITE = 1;
    const DUPLICATES_IGNORE = 2;
    
    private $_templates = array(); // template library
    private $_templatesRendered = array(); // temporary array for reducing work done, and gaining speed at the cost of memory
    
    /**
     * Parses input string to find templates, then adds templates to the template library
     *
     * @param string $input The string containing templates
     * @param int $dupes The default action to take if a duplicate template is found
     *     default: Throw exception
     *     TEMPLATE_OVERWRITE: overwrite existing templates with new ones
     *     TEMPLATE_IGNORE: ignore duplicate templates (keep the original)
     * @throws TemplateParseException There was an error parsing the input
     * @throws TemplateDuplicateException A duplicate template was found, but no
     *              duplicate action was defined.
     */
    public function parseTemplates($input, $dupes = 0) {
        // split the input at the new line. We're doing this old school - one line at a time
        $inputArray = explode("\n", $input);
        
        // initial state for templates - empty
        $template = null;
        $templateStarted = -1;
        $templateLines = array();
        
        // and let's go
        foreach ($inputArray as $lineNo => $line) {
            // we don't want index counting here - this is for user display
            $lineNo = $lineNo + 1;
            
            // are we searching for a new template to start?
            if ($template === null) {
                // make a blank array for matches
                $matches = array();
                if (preg_match('/<%%STARTTEMPLATE ([-0-9A-Za-z_\.]+)%%>/', $line, $matches)) {
                    // we've got the start of a template! Let's get the name of the template
                    $template = $matches[1];
                    $templateStarted = $lineNo;
                    
                    // check if we already have this template in the template library,
                    //     or if we're OK to overwrite it
                    if (!isset($this->_templates[$template]) || ($dupes & TEMPLATE_OVERWRITE)) {
                        // clear the junk from the first line (i.e. anything up to the %%> that defines the template as having started)
                        $line_clean = preg_replace('/^.*<%%STARTTEMPLATE '.$template.'%%>/', '', $line);
                        // only add this line if there's non-whitespace
                        if (!preg_match('/^\s*$/', $line_clean)) {
                            // see if we're ending the template on this line, too
                            if (preg_match('/<%%ENDTEMPLATE '.$template.'%%>/', $line_clean)) {
                                // remove the end template and all trailing junk
                                $line_clean = preg_replace('/<%%ENDTEMPLATE '.$template.'%%>.*$/', '', $line_clean);
                                
                                // build the template
                                $temp = new Template($line_clean, $template);
                                
                                // add the template to the library
                                $this->add_template($temp, $dupes);
                                
                                // reset the template fields
                                $template = null;
                                $templateStarted = -1;
                                $templateLines = array();
                            } else {
                                // otherwise just add the line to the template, and move on
                                $templateLines[] = $line_clean;
                            }
                        }
                    } else if ($dupes & TEMPLATE_IGNORE) {
                        // ignore the duplicate template
                        $template = null;
                        $templateStarted = -1;
                        $templateLines = array();
                    } else {
                        throw new TemplateDuplicateException('Error while parsing templates on line '.$lineNo.'. Template "'.$template.'" already exists in library, and no duplicate action is defined');
                    }
                } // no "STARTTEMPLATE" declaration - ignore this line.
            } else {
                if (preg_match('/<%%ENDTEMPLATE '.$template.'%%>/', $line)) {
                    // remove the end template and all trailing junk from the line
                    $line_clean = preg_replace('/<%%ENDTEMPLATE '.$template.'%%>.*$/', '', $line);
                    
                    // add to the line
                    $templateLines[] = $line_clean;
                    
                    // build the template
                    $temp = new Template(implode("\n", $templateLines), $template);
                    
                    // add the template to the library
                    $this->addTemplate($temp, $dupes);
                    
                    // reset the template fields
                    $template = null;
                    $templateLines = array();
                } else {
                    // otherwise just add the line to the template, and move on
                    $templateLines[] = $line;
                }
            }
        }
        
        // check that the last template we were looking for has finished
        if ($template !== null) {
            throw new TemplateParseException('Error parsing templates. No "ENDTEMPLATE" found for "'.$template.'" (started on line '.$templateStarted.')');
        }
    }
    
    /**
     * Adds a template to the template library
     *
     * @param Template $template The template to add.
     * @param int $dupes The default action to take if a duplicate template is found
     *     default: Throw exception
     *     TEMPLATE_OVERWRITE: overwrite existing templates with new ones
     *     TEMPLATE_IGNORE: ignore duplicate templates (keep the original)
     * @throws TemplateDuplicateException A duplicate template was found, but no
     *              duplicate action was defined.
     */
    public function addTemplate(Template $template, $dupes = 0) {
        if (!isset($this->_templates[$template->getName()]) || ($dupes & TEMPLATE_OVERWRITE)) {
            $this->_templates[$template->getName()] = $template;
        } else if ($dupes & TEMPLATE_IGNORE) {
            // ignore the duplicate template
        } else {
            throw new TemplateDuplicateException('Cannot add template "'.$template.'" as it already exists in library, and no duplicate action is defined');
        }
    }
    
    /**
     * Merges templates from another engine into this one.
     *
     * @param Engine $other The other template engine.
     * @param int $dupes The default action to take if a duplicate template is found
     *     default: Throw exception
     *     TEMPLATE_OVERWRITE: overwrite existing templates with new ones
     *     TEMPLATE_IGNORE: ignore duplicate templates (keep the original)
     * @throws TemplateDuplicateException A duplicate template was found, but no
     *              duplicate action was defined.
     */
    public function mergeTemplates(TemplateEngine $other, $dupes = 0) {
        $other_templates = $other->listTemplates();
        
        // Go through the list of templates from the other template engine
        foreach ($other_templates as $template) {
            // if we don't have the template, or are set to overwrite templates, add it.
            if (!isset($this->_templates[$template]) || ($dupes & TEMPLATE_OVERWRITE)) {
                $this->_addTemplate($other->_getTemplate($template), $dupes);
            } else if ($dupes & TEMPLATE_IGNORE) {
                // ignore the duplicate template
            } else {
                throw new TemplateDuplicateException('Cannot merge template "'.$template.'" as it already exists in library, and no duplicate action is defined');
            }
        }
    }
    
    /**
     * Renders a template from the template library.
     *
     * <p>It will automatically fill slots in the template with corresponding
     * data from the slots array, and will call in and render templates required
     * from the template library.
     *
     * @param string $templateName The name of the template to render
     * @param array $slots An array of data to place in named slots
     * @return string The rendered template
     * @throws TemplateMissingException A required template could not be found in
     *              the template library.
     */
    public function render($templateName, $slots) {
        if(isset($this->_templates[$templateName])) {
            // get a hash for this template name and slots value
            $hash = md5($templateName."||".var_export($slots,true));
            
            // assume that anything matching the same hash would render the same
            if (!isset($this->_templatesRendered[$hash])) {
                $temp = $this->_templates[$templateName];
                $this->_templatesRendered[$hash] = $temp->render($slots, $this);
            }
            
            // return the stored render
            return $this->_templatesRendered[$hash];
        } else {
            throw new TemplateMissingException('Template "'.$templateName.'" does not exist, or may not have been loaded.');
        }
    }
    
    /**
     * Returns a list of all template names in the template library
     *
     * @return array The names of all templates in the library
     */
    public function listTemplates() {
        return array_keys($this->_templates);
    }
    
    /**
     * Gets a named template from the template library
     *
     * @param string $templateName The name of the requested template
     * @return Template The requested template
     * @throws TemplateMissingException If the template does not exist in the
     *              template library.
     */
    public function getTemplate($templateName) {
        if (isset($this->_templates[$templateName])) {
            return $this->_templates[$templateName];
        } else {
            throw new TemplateMissingException('Requested template "'.$templateName.'" not found in template library');
        }
    }
}