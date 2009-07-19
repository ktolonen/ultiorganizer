function generalUserMenu(id) {
    this.menuText = new Array();
    this.menuLink = new Array();
    this.menuId;
    
    this.menuId = id;
    
    this.addMenuLine = addMenuLine;
    
    this.maxLength = maxLength;
}

function maxLength() {
    return this.menuText.length;
}

function addMenuLine(text, link) {
    var maxIndex;
    
    if (this.menuText) {
        maxIndex = this.menuText.length;
    } else {
        maxIndex = 0;
    }
    this.menuText[maxIndex] = text;
    this.menuLink[maxIndex] = link;
}
