<?php

namespace lhweb\view;

/**
 * Description of LHFCheckbox
 *
 * @author loki
 */
class LHFCheckbox extends LHFormField {
    protected $text  = null;
    protected $width = null;
    protected $value = 1;
    protected $checked = false;
    protected $class = "checkbox-inline";
    protected $class_checkbox = "checkbox";
    protected $help  = "";
    protected $onclick = "";
    protected $onchange = "";
    
    public function render() {
        if($this->text === null) {
            $this->text = ucwords($this->id) . ":";
        }
        
        $txt = "";
        if($this->width > 0) {
            $txt .=  "<div class='col-sm-$this->width'>";
        }
        
        $txt .= "<label id='label_$this->id' class='$this->class'";
        $txt .= $this->renderHtmlAttr("style");
        $txt .= $this->renderData();
        $txt .= ">";
        $txt .= "<input type='checkbox' class='$this->class_checkbox' ";
        $txt .= $this->renderHtmlAttr("id");
        $txt .= $this->renderHtmlAttr("name");
        $txt .= $this->renderHtmlAttr("value");
        $txt .= $this->renderHtmlProp("checked");
        $txt .= $this->renderHtmlProp("required");
        $txt .= $this->renderHtmlProp("disabled");
        $txt .= $this->renderHtmlAttr("onclick");
        $txt .= $this->renderHtmlAttr("onchange");
        $txt .= $this->renderData();
        $txt .= "/>";
        
        $txt .= " $this->text";
        
        if($this->help){
            $txt .= " <span class=\"glyphicon glyphicon-question-sign info\" style=\"cursor: help;\" ";
            $txt .= "title=\"" . htmlentities($this->help) . "\">";
            $txt .= "</span>";
        }
        
        $txt .= "</label>";
        
        
        if($this->width > 0){
            $txt .=  "</div>";
        }
        
        return $txt;
    }
}
