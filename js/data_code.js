
// contains data operations for vsteno-editor (import / export etc.)

var tokenPullDownSelection = [];
var actualFont = new ShorthandFont();
var otherFont = new ShorthandFont();

function filterOutEmptySpaces(string) {
	var newString = string;
	do {
		string = newString;
		newString = string.replace(/\s+/, '');
	} while (newString != string);
	return newString;
}

// workaround for browsers where innerHTML with select-tag doesn't work
/* doesn't work ... */
function swapInnerHTML(objID,newHTML) {
  var el=document.getElementById(objID);
  el.outerHTML=el.outerHTML.replace(el.innerHTML+'</select>',newHTML+'</select>');
}
// another workaround ... one guy came up with the absurd recursive solution ... I love absurd solution for absurd problems ... :-) :-) :-)
/* ... but doesn't work neither ...
function setInnerHTML(element, html, count) {
     element.innerHTML = html;
     if(!count)
         count = 1;
     if(html != '' && element.innerHTML == '' && count < 5) {
         ++count;
         setTimeout( function() {
             setInnerHTML( element, html, count );
         }, 50 );
     }
}
*/
 
// general functions
function addNewTokenToPullDownSelection(token) {
	token = filterOutEmptySpaces(token); // filter out empty spaces 
	if ((tokenPullDownSelection.indexOf(token) == -1)  && (token != "")) {	// element doesn't exist => add
		tokenPullDownSelection.push(token);
		tokenPullDownSelection.sort(); // sort array alphabetically
		updatePullDownSelection(token);
	}
	// set textfield to empty
	document.getElementById("token").value = "";
}
function updatePullDownSelection(token) {			// preselect token in list
	var optionList = "<option value=\"select\">-select-</option>\n";
	var preselection = tokenPullDownSelection.indexOf(token); // returns -1 if array doesn't contain token
	for (var i=0;i<tokenPullDownSelection.length; i++) {
		if (i == preselection) optionList += "<option value=\"" + tokenPullDownSelection[i] + "\" selected>" + tokenPullDownSelection[i] + "</option>\n";
		else optionList += "<option value=\"" + tokenPullDownSelection[i] + "\">" + tokenPullDownSelection[i] + "</option>\n"; 
	}
	//var element = document.getElementById("tokenpulldown");
	//element.innerHTML = optionList;	
	// use workaround
	swapInnerHTML("tokenpulldown", optionList); ; // doesn't work (i.e. works, but doesn't solve the problem/incompatibility with other browsers)
	// use another workaround
	// var element = document.getElementById("tokenpulldowndiv");
	// element.innerHTML = "<select id=\"tokenpulldown\">" + optionList + "</select>";
	// try another workaround
	// setTimeout( function() { element.innerHTML = optionList; }, 50 );
	// and one more ...
	// setInnerHTML(element, optionList, 0);
	// element.InnerText = optionList;
	
	
	// other ideas: use document.getElementById('day').options.add(new Option("1", "1")); with: new Option("optionText", "optionValue")
	
	
	// ok, greetings to all Apple and Windows users: get yourself a Linux ... ! :-)
	// Safari: doesn't work
	// Android (stock browser): doesn't work
	// Android (Chrome): works
	// IE: untested
	// Firefox (original): mainly untested (Mac: pulldown menu works, but bottons don't?!)
	// Firefox (clones): derived versions like IceCat and ABrowser under Linux work
}
function createPullDownSelectionFromActualFont() {
	// deletes tokenPullDownSelection and creates a new array with elements from actualFont
	// can be used to "load" a new font and adjust pulldown list accordingls (for exemple if editor is used for SE1)
	// delete actual list
	//console.log("create pullDownSelectionFromActualFont");
	tokenPullDownSelection.length = 0;
	// create new list
	for (var token in actualFont.tokenList) {
		tokenPullDownSelection.push(token);
	}
	updatePullDownSelection(""); // don't select anything but update html list
}



// classes 
// class ShorthandFont
function ShorthandFont() {
	this.tokenList = {}; 
	this.editorData = {};
}

// all the following methods have to be replaced by global functions due to data import from PHP (see below)
/*
ShorthandFont.prototype.saveTokenAndEditorData = function(token) {		// saves actual token to this.tokenList["token"]
	if ((token != "select") && (token != "empty")) {
		this.deleteTokenAndEditorData(token);
		this.tokenList[token] = new TokenDefinition();			// data will be copied directly via constructor that call goAndGrabThatTokenData()-method
		this.editorData[token] = new EditorParameters();		// same for editor data
	}
	
	console.log("ShorthandFont: ", this);
	console.log("editor: ", mainCanvas.editor);
}
ShorthandFont.prototype.deleteTokenFromPullDownSelection = function(token) {
	this.deleteTokenData(token);
	var index = tokenPullDownSelection.indexOf(token);
	if (index > -1) {	// element does exist => delete it
		tokenPullDownSelection.splice(index, 1);
		updatePullDownSelection();
	}
}
ShorthandFont.prototype.deleteTokenAndEditorData = function(token) {
	this.deleteTokenData(token);
	this.deleteEditorData(token);
}
ShorthandFont.prototype.deleteTokenData = function(token) {
	//console.log("deleteTokenData");
	this.tokenList[token] = null;
}
ShorthandFont.prototype.deleteEditorData = function(token) {
	//console.log("deleteEditorData");
	this.editorData[token] = null;	
}
ShorthandFont.prototype.loadTokenAndEditorData = function(token) {
	if (actualFont.editorData != null) mainCanvas.editor.loadAndInitializeEditorData(actualFont.editorData[token]);
	else console.log("don't (re)set editor data ... (null)");
	mainCanvas.editor.loadAndInitializeTokenData(actualFont.tokenList[token]);
}
*/

// implement ShorthandFont methods the procedural way .. reason: when ShorthandFont is imported from PHP, only
// data is present (no methods) => it's better to pass only the data to a function instead of trying to "repair"
// broken (inexistent) objects method in PHP datastructure.

function loadTokenAndEditorData(token) {
	//console.log("procedural loadTokenAndEditorData()");
	//if (actualFont.editorData != null) mainCanvas.editor.loadAndInitializeEditorData(actualFont.editorData[token]);
	//else console.log("don't (re)set editor data ... (null)");
	//mainCanvas.editor.loadAndInitializeTokenData(actualFont.tokenList[token]);		// ok, that works!
	mainCanvas.editor.loadAndInitializeTokenData(token);		// ok, that works! call function with tokenKey, so that it can access editorData
	//mainCanvas.editor.loadAndInitializeEditorData(actualFont.editorData[token]); // load editor data first, so that knots can be connected to parallelRotatingAxis => doesn't work ...
	mainCanvas.editor.loadAndInitializeEditorData(token); // load editor data first, so that knots can be connected to parallelRotatingAxis => doesn't work ... // call function with tokenKey so that it can access tokenList data
	
	//console.log("focus1: ", document.activeElement);
	//document.getElementById("drawingArea").focus();
	document.getElementById("load").blur(); // correct focus
	// set editor mode (visibility: middle line or outerShape)
	//console.log("set editor mode: ", actualFont);
	if ((actualFont.version != undefined) && (actualFont.version == "SE1")) {
		mainCanvas.editor.showMiddlePathWithVariableWidth(); // outerShape still active for the moment
		console.log("set middle path mode - mainCanvas.editor: ", mainCanvas.editor);
	} else {
		mainCanvas.editor.hideMiddlePathWithVariableWidth(); // supposing that outer shape is still active
	}
}
function saveTokenAndEditorData(token) {		// saves actual token to this.tokenList["token"]
	console.log("freehand_code: saveTokenAndEditorData()");
	// ok, guess what: when this function is invoked, instead of writing the data, it deletes it ... meaning that token in actualFont.tokenList
	// is set to null. Don't even know how I got a working data export the last time ...
	// This editor is a nightmare ... ! 3 months of development and still completely useless (good for a screenshot in the best case ...)
	// Anyway: I discovered why . , : ; ? ! were drawn wrong (with connection) in spanisch model: because offset 22 in header was deleted
	// (0 instead of 1). But no idea how to fix that for the moment: nothing works ...
	// In freehand_code.js: function TEEditableToken.prototype.copyTextFieldsToHeaderArraySE1 isn't even able to read correct values from
	// HTML-elements (it's always the same value - clicking on radio buttons has no effect at all ... !!!)
	// God ... I hate JS ... :)
	
	if ((token != "select") && (token != "empty")) {
		deleteTokenAndEditorData(token);
		//console.log("token and editor data deleted");
		//console.log("create TokenDefinition object");
		actualFont.tokenList[token] = new TokenDefinition();			// data will be copied directly via constructor that call goAndGrabThatTokenData()-method
		//console.log("create EditorParameters object");
		actualFont.editorData[token] = new EditorParameters();		// same for editor data
	}
	
	console.log("ShorthandFont: ", actualFont);
	console.log("Editor: ", mainCanvas.editor);
	document.getElementById("save").blur(); // correct focus
}
function deleteTokenFromPullDownSelection(token) {
	deleteTokenData(token);
	var index = tokenPullDownSelection.indexOf(token);
	if (index > -1) {	// element does exist => delete it
		tokenPullDownSelection.splice(index, 1);
		updatePullDownSelection();
	}
	document.getElementById("delete").blur(); // correct focus
}
function deleteTokenAndEditorData(token) {
	//console.log("delete token and editor data (function)");
	deleteTokenData(token);
	deleteEditorData(token);
}
function deleteTokenData(token) {
	//console.log("deleteTokenData");
	actualFont.tokenList[token] = null;
}
function deleteEditorData(token) {
	//console.log("deleteEditorData");
	actualFont.editorData[token] = null;	
}


// database data types
// class TokenDefinition
function TokenDefinition() {
	this.header = null;
	this.tokenData = [];
	this.goAndGrabThatTokenData();
}
TokenDefinition.prototype.goAndGrabThatTokenData = function() {
	mainCanvas.editor.editableToken.copyTextFieldsToHeaderArray();
	if ((actualFont.version != undefined) && (actualFont.version == "SE1")) {	// header for SE1 must be recreated from human readable webpage format
		mainCanvas.editor.editableToken.copyTextFieldsToHeaderArraySE1();
		this.header = mainCanvas.editor.editableToken.header.slice(); 
		
	} else {	// use standard (flat) array
		this.header = mainCanvas.editor.editableToken.header.slice(); 
		// well, guess what ... slice() is vital here ... otherwise JS will make this.header point to one and the same object 
		// (and operations destined for this token will affect other objects also ... ceterum censeo ;-))
		// to resume: slice() <=> copy by value
		// well, guess what (2): I just discovered that JSON doesn't stringify my arrays ... that means that I will have to rewrite the whole
		// data structure as objects ... oh, I really like this JS ...
		// ok, I've verified that JSON CAN stringify arrays ... but apparently arrays are not always arrays in JS (... oh yeah: why 
		// not add a little bit more to the confusion). For short: "normal" arrays only can have numeric indices and can be stringified, 
		// but as soon as you try to create something similar to an associative array (e.g. var a = []; a["info"] = "foo";), JS converts 
		// this array to a standard-object ... Now, you might think: ok, if my array gets converted to an object (and an object can be
		// stringified) then my ex-array-now-object should be stringifiable, but no, you're wrong: what you've created is a "neither-nor"
		// data structure which won't get stringified (some people call it the "JSON array bizarreness" ... I'd just say: ceterum censeo ...:)
		// Anyway, conclusion (or lesson learned): if you wan't to use JSON for your data use either pure objects or pure arrays 
		// (and even combinations of the two) but not "associative arrays"  (even if - bizarrely - they DO work in your running code). 
		// There's nothing like "associative arrays" in JS. The closest thing to an associative array (like in PHP for example) is
		// an object! In other words: var a = {}; a["info"] = "foo"; OR: a.info = "foo"; The annoying thing is that you can't access
		// the elements with a numeric index afterwards (so a[0] won't work ...)
	
		//console.log("goAndGrabThatTokenData: header: ", this.header);
	}
	
	this.getTokenDefinition();
}
TokenDefinition.prototype.getTokenDefinition = function() {
	for (var i=0; i<mainCanvas.editor.editableToken.knotsList.length; i++) {
		this.tokenData.push(new DBKnotData(i));	
	}
}

// class EditorParameters
function EditorParameters() {
	this.rotatingAxisList = [];
	this.goAndCollectThatEditorData();
}
EditorParameters.prototype.goAndCollectThatEditorData = function() {
	for (var i=0; i<mainCanvas.editor.rotatingAxis.parallelRotatingAxis.newAxisList.length; i++) {
		if (mainCanvas.editor.rotatingAxis.parallelRotatingAxis.newAxisList[i].type != "main") { // don't save main axis
			this.rotatingAxisList.push(mainCanvas.editor.rotatingAxis.parallelRotatingAxis.newAxisList[i].shiftX);
		}
	}
}

// class DBTokenData
function DBKnotData(index) {
	this.knotType = null;
	this.calcType = null; 	// horizontal, orthogonal, proportional
	this.vector1 = null;
	this.vector2 = null;
	this.shiftX = null;
	this.shiftY = null;
	this.tensions = null;
	this.thickness = {};
	// call function to define variables
	return this.readKnotData(index);
}
DBKnotData.prototype.readKnotData = function(index) {
	this.knotType = mainCanvas.editor.editableToken.knotsList[index].type;
	this.calcType = mainCanvas.editor.editableToken.knotsList[index].linkToRelativeKnot.type;
	this.vector1 = mainCanvas.editor.editableToken.knotsList[index].linkToRelativeKnot.rd1;
	this.vector2 = mainCanvas.editor.editableToken.knotsList[index].linkToRelativeKnot.rd2;
	this.shiftX = mainCanvas.editor.editableToken.knotsList[index].shiftX;
	this.shiftY = mainCanvas.editor.editableToken.knotsList[index].shiftY;
	this.tensions = mainCanvas.editor.editableToken.knotsList[index].tensions;
	this.thickness["standard"] = {}; 	// I'm pretty sure there's another syntax for this in JS, but as I said: ceterum censeo ... ;-)
	this.thickness["shadowed"] = {};
	this.thickness.standard["left"] = mainCanvas.editor.editableToken.leftVectors[0][index].distance;		// make data more readable with associative array
	this.thickness.standard["right"] = mainCanvas.editor.editableToken.rightVectors[0][index].distance;	    // hugh ... copying array element by element ... this 'll be slow ... (but who cares ... ;-)
	this.thickness["shadowed"]["left"] = mainCanvas.editor.editableToken.leftVectors[1][index].distance;
	this.thickness["shadowed"]["right"] = mainCanvas.editor.editableToken.rightVectors[1][index].distance;
	
	
	/*this.thickness["standard"] = []; 	// I'm pretty sure there's another syntax for this in JS, but as I said: ceterum censeo ... ;-)
	this.thickness["shadowed"] = [];
	this.thickness["standard"]["left"] = mainCanvas.editor.editableToken.leftVectors[0][index].distance;		// make data more readable with associative array
	this.thickness["standard"]["right"] = mainCanvas.editor.editableToken.rightVectors[0][index].distance;	    // hugh ... copying array element by element ... this 'll be slow ... (but who cares ... ;-)
	this.thickness["shadowed"]["left"] = mainCanvas.editor.editableToken.leftVectors[1][index].distance;
	this.thickness["shadowed"]["right"] = mainCanvas.editor.editableToken.rightVectors[1][index].distance;
	*/
	return this;
}
