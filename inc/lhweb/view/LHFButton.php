<?php
namespace lhweb\view;

/**
 * Representa o objeto html <button>
 *
 * @author loki
 */
class LHFButton  extends LHFormField {
    protected $class = "btn";
    protected $text  = "";
    protected $title = "";
    protected $icon  = null;
    protected $iconSide = 1;
    protected $type  = "button";
    
    public function render() {
        echo "<button ";
        $this->renderHtmlAttr("id");
        $this->renderHtmlAttr("class");
        $this->renderHtmlAttr("type");
        $this->renderHtmlAttr("title");
        $this->renderHtmlProp("disabled");
        $this->renderData();
        echo ">";
        
        if($this->icon !== null && $this->iconSide === static::$LEFT){
            $this->renderGlyphIcon($this->icon);
        }
        
        echo htmlspecialchars($this->text);
        
        if($this->icon !== null && $this->iconSide === static::$RIGHT){
            $this->renderGlyphIcon($this->icon);
        }
        echo "</button>";
    } // render

}
