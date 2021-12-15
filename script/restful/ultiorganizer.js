Date.prototype.setISO8601 = function (string) {
	var regexp = "([0-9]{4})(-([0-9]{2})(-([0-9]{2})" +
	"(T([0-9]{2}):([0-9]{2})(:([0-9]{2})(\.([0-9]+))?)?" +
	"(Z|(([-+])([0-9]{2}):([0-9]{2})))?)?)?)?";
	var d = string.match(new RegExp(regexp));

	var offset = 0;
	var date = new Date(d[1], 0, 1);

	if (d[3]) { date.setMonth(d[3] - 1); }
	if (d[5]) { date.setDate(d[5]); }
	if (d[7]) { date.setHours(d[7]); }
	if (d[8]) { date.setMinutes(d[8]); }
	if (d[10]) { date.setSeconds(d[10]); }
	if (d[12]) { date.setMilliseconds(Number("0." + d[12]) * 1000); }
	if (d[14]) {
		offset = (Number(d[16]) * 60) + Number(d[17]);
		offset *= ((d[15] == '-') ? 1 : -1);
	}

	offset -= date.getTimezoneOffset();
	time = (Number(date) + (offset * 60 * 1000));
	this.setTime(Number(time));
};

Date.prototype.toISO8601String = function (format, offset) {
	/* accepted values for the format [1-6]:
     1 Year:
       YYYY (eg 1997)
     2 Year and month:
       YYYY-MM (eg 1997-07)
     3 Complete date:
       YYYY-MM-DD (eg 1997-07-16)
     4 Complete date plus hours and minutes:
       YYYY-MM-DDThh:mmTZD (eg 1997-07-16T19:20+01:00)
     5 Complete date plus hours, minutes and seconds:
       YYYY-MM-DDThh:mm:ssTZD (eg 1997-07-16T19:20:30+01:00)
     6 Complete date plus hours, minutes, seconds and a decimal
       fraction of a second
       YYYY-MM-DDThh:mm:ss.sTZD (eg 1997-07-16T19:20:30.45+01:00)
	 */
	if (!format) { var format = 6; }
	if (!offset) {
		var offset = 'Z';
		var date = this;
	} else {
		var d = offset.match(/([-+])([0-9]{2}):([0-9]{2})/);
		var offsetnum = (Number(d[2]) * 60) + Number(d[3]);
		offsetnum *= ((d[1] == '-') ? -1 : 1);
		var date = new Date(Number(Number(this) + (offsetnum * 60000)));
	}

	var zeropad = function (num) { return ((num < 10) ? '0' : '') + num; };

	var str = "";
	str += date.getUTCFullYear();
	if (format > 1) { str += "-" + zeropad(date.getUTCMonth() + 1); }
	if (format > 2) { str += "-" + zeropad(date.getUTCDate()); }
	if (format > 3) {
		str += "T" + zeropad(date.getUTCHours()) +
		":" + zeropad(date.getUTCMinutes());
	}
	if (format > 5) {
		var secs = Number(date.getUTCSeconds() + "." +
				((date.getUTCMilliseconds() < 100) ? '0' : '') +
				zeropad(date.getUTCMilliseconds()));
		str += ":" + zeropad(secs);
	} else if (format > 4) { str += ":" + zeropad(date.getUTCSeconds()); }

	if (format > 3) { str += offset; }
	return str;
};

String.prototype.trim = function(){
	return	(this.replace(/^[\s\xA0]+/, "").replace(/[\s\xA0]+$/, ""));
};

String.prototype.startsWith = function(str) {
	return (this.match("^"+str)==str);
};
	
String.prototype.endsWith = function(str) {
	return (this.match(str+"$")==str);
};
String.prototype.capitalize = function() {
	return this.replace( /(^|\s)([a-z])/g , function(m,p1,p2){ return p1+p2.toUpperCase(); } );
};
	  
ultiorganizer = function(baseUrl) {
	var _baseUrl = baseUrl;
	this.getUrl = function() {
		return _baseUrl;
	};
	var seasonsFetcher = new ultiorganizer.Fetcher(this, 'seasons', ultiorganizer.Season);
	this.getSeasonArray = seasonsFetcher.fetchArray;
	this.getSeason = seasonsFetcher.fetchItem;

};

ultiorganizer.Fetcher = function(parent, listname, child) {
	var _parent = parent;
	var _listname = listname;
	var _child = child;
	this.fetchArray = function(extraParams, callback) {
		var _callback = {
				success: function(o) {
			var html = o.responseXML.documentElement;
			var divs = html.getElementsByTagName('div');
			var parent = o.argument.parent;
			var list = o.argument.list;
			var ret = [];
			for (var i=0; i < divs.length; i++) {
				if (divs[i].getAttribute('class') == 'list') {
					var links = divs[i].getElementsByTagName('a');
					for (var j=0; j< links.length; j++) {
						var id = links[j].getAttribute('href').substring(1);
						id = id.substring(parent.getUrl().length + list.length + 2);
						var name = links[j].innerHTML;
						ret[j] = new child(id, name, parent, list);
					}
				}
			}
			o.argument.callback(ret);
		},
		failure: function(o) {
			o.argument.callback([]);
		},
		argument: { callback : callback, list: _listname, parent: _parent, child : _child }
		};
		YAHOO.util.Connect.asyncRequest('GET', parent.getUrl() + '/' + listname + extraParams, _callback, null);
	};

	this.fetchItem = function(id) {
		var _callback = {
				success: function(o) {
			var html = o.responseXML.documentElement;
			var parent = o.argument.parent;
			var child = o.argument.child;
			var list = o.argument.list;
			var params = html.getElementsByTagName('input');
			var ret = new child(id, null, parent, list);
			for (var i=0; i < params.length; i++) {
				var type = params[i].getAttribute('class');
				var name = params[i].getAttribute('name');
				var value = params[i].getAttribute('value');
				var manipulator = null;
				if (type.endsWith('text')) {
					manipulator = new ultiorganizer.StringAccessor(ret, name);
				} else if (type == 'date') {
					manipulator = new ultiorganizer.DateAccessor(ret, name);
				} else if (type == 'boolean') {
					manipulator = new ultiorganizer.BoolAccessor(ret, name);
				} else if (type.startsWith(enum)) {
					manipulator = new ultiorganizer.EnumAccessor(ret, name, type);
					ret['_' + name] = manipulator;
				}
				ret['get' + name.capitalize()] = manipulator.getValue;
				ret['set' + name.capitalize()] = manipulator.setValue;
				manipulator.setValue(value);
			}
			ret.dirty = false;
			o.argument.callback(ret);
		},
		failure: function(o) {
			o.argument.callback(null);
		},
		argument: { callback : callback, list: _listname, parent: _parent, child : _child }
		};
		YAHOO.util.Connect.asyncRequest('GET', baseUrl + '?' + listname + extraParams, _callback, null);
	};
};

ultiorganizer.StringAccessor = function(parent, name) {
	var _parent = parent;
	var _name = name;
	this.setValue = function(value) {
		var old = _parent['_' + _name];
		if (typeof value == 'string') {
			_parent['_' + _name] = value;
			if (old != _parent['_' + _name]) {
				_parent.dirty = true;
			}
		} else if (typeof value.toString == 'function') {
			_parent['_' + _name] = value.toString();	
		}
		if (old != _parent['_' + _name]) {
			_parent.dirty = true;
		}
	};
	this.getValue = function() {
		return _parent['_' + _name];
	};
};

ultiorganizer.DateAccessor = function(parent, name) {
	var _parent = parent;
	var _name = name;
	this.setValue = function(value) {
		var old = _parent['_' + _name];
		if (typeof value == 'string') {
			var date = new Date();
			date.setISO8601(value);
			_parent['_' + _name] = date;
		} else if (typeof value.getYear() == 'function') {
			_parent['_' + _name] = value;
		}
		if (old != _parent['_' + _name]) {
			_parent.dirty = true;
		}
	};
	this.getValue = function() {
		return _parent['_' + _name];
	};
};

ultiorganizer.BoolAccessor = function(parent, name) {
	var _parent = parent;
	var _name = name;
	this.setValue = function(value) {
		var old = _parent['_' + _name];
		if (typeof value == 'string') { 
			if (value == '1' || value == 'yes' || value =='true') {
				_parent['_' + _name] = true;
			} else {
				_parent['_' + _name] = false;
			}
		} else if (typeof value == 'boolean') {
			_parent['_' + _name] = value;
		}
		if (old != _parent['_' + _name]) {
			_parent.dirty = true;
		}
	};
	this.getValue = function() {
		return _parent['_' + _name];
	};
};

ultiorganizer.EnumAccessor = function(parent, name, type) {
	var _parent = parent;
	var _name = name;
	var options = type.split(' ');
	this.values = [];
	this.descriptions = [];
	this.selected = null;
	for (var i=1; i<options.length; i++) {
		var splitted = options[i].split(':');
		var descr = decodeURIComponent(splitted[1]);
		values[i-1] = splitted[0];
		descriptions[i-1] = descr;
		this[splitted[0]] = descr;
	}
	
	this.setValue = function(value) {
		if (typeof this[value] == 'string' && selected != value) {
			selected = value;
			_parent.dirty = true;
		}
	};
	this.getValue = function() {
		return selected;
	};
};

ultiorganizer.Item = function(id, name, parent, listName) {
	var _id = id;
	var _name = name;
	var _parent = parent;
	var _listName = listName;
	
	var nameAccessor = new ultiorganizer.StringAccessor(this, 'name');
	this.getName = nameAccessor.getValue;
	this.setName = nameAccessor.setValue;
	
	this.alert = function() {
		alert(_id + ": " + _name);
	};

	this.getLinkNS = function(namespace) {
		var link = document.createElementNS(namespace, 'a');
		link.setAttribute('href', this.getUrl());
		link.appendChild(document.createTextNode(_name));
		return link;
	};

	this.getUrl = function() {
		return parent.getUrl() + "/" + _listName + '/' + _id;
	};
};

ultiorganizer.Season = new Object();
ultiorganizer.Season.prototype = new ultiorganizer.Item;
ultiorganizer.Season.prototype.constructor = function(id, name, parent, listName) {
	ultiorganizer.Item.call(this);
	
}
