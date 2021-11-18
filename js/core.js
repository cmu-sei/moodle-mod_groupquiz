// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package   mod_groupquiz
 * @author    John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright 2014 University of Wisconsin - Madison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// assign top level namespace
var groupquiz = groupquiz || {};
groupquiz.vars = groupquiz.vars || {};

/**
 * Adds a variable to the groupquiz object
 *
 * @param name the name of the property to set
 * @param value the value of the property to set
 * @returns {groupquiz}
 */
groupquiz.set = function (name, value) {
    this.vars[name] = value;
    return this;
};

/**
 * Gets a variable from the groupquiz object
 *
 * @param name
 * @returns {*}
 */
groupquiz.get = function (name) {
    if (typeof this.vars[name] === 'undefined') {
        return 'undefined';
    }

    return this.vars[name];
};

/**
 * Defines ajax functions in its namespace
 *
 *
 * @type {{httpRequest: {}, init: init, create_request: create_request}}
 */
groupquiz.ajax = {

    httpRequest: {},

    init: function () {

    },
    /**
     * Create and send a request
     * @param url the path to the file you are calling, note this is only for local requests as siteroot will be added to the front of the url
     * @param params the parameters you'd like to add.  This should be an object like the following example
     *
     *          params = { 'id' : 1, 'questionid': 56, 'answer': 'testing' }
     *
     *                  will convert to these post parameters
     *
     *          'id=1&questionid=56&answer=testing'
     *
     * @param callback callable function to be the callback onreadystatechange, must accept httpstatus and the response
     */
    create_request: function (url, params, callback) {

        // re-init a new request ( so we don't have things running into each other)
        if (window.XMLHttpRequest) { // Mozilla, Safari, ...
            var httpRequest = new XMLHttpRequest();
            if (httpRequest.overrideMimeType) {
                httpRequest.overrideMimeType('text/xml');
            }
        } else if (window.ActiveXObject) { // IE
            try {
                var httpRequest = new ActiveXObject("Msxml2.XMLHTTP");
            }
            catch (e) {
                try {
                    httpRequest = new ActiveXObject("Microsoft.XMLHTTP");
                }
                catch (e) {
                    alert(window.M.utils.get_string('httprequestfail', 'groupquiz'));
                }
            }
        }

        httpRequest.onreadystatechange = function () {
            if (this.readyState == 4) {

                var status = this.status;
                var response = '';
                if (status == 500) {
                    try {
                        response = JSON.parse(this.responseText);
                    } catch (Error) {
                        response = '';
                    }
                } else {
                    try {
                        response = JSON.parse(this.responseText);
                    } catch (Error) {
                        response = this.responseText;
                    }

                }
                callback(status, response); // call the callback with the status and response
            }
        };
        httpRequest.open('POST', groupquiz.get('siteroot') + url, true);

        var parameters = '';
        if (params instanceof FormData) {
            parameters = params;  // already valid to send with xmlHttpRequest
        } else { // separate it out
            httpRequest.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');

            for (var param in params) {
                if (params.hasOwnProperty(param)) {
                    if (parameters.length > 0) {
                        parameters += '&';
                    }
                    parameters += param + '=' + encodeURI(params[param]);
                }
            }
        }

        httpRequest.send(parameters);

    }
};


/**
 * Wrapper for handle_question when the user clicks save
 *
 */
groupquiz.save_question = function (qid) {
    

    // TODO pass this in via the button being clicked
    var currentquestion = qid.substring(1);

    // current question refers to the slot number

    this.handle_question(currentquestion);
};
/**
 * Callback for when the quiz page is fully loaded
 * The stop parameter is there just so that people see the loading on fast browsers
 *
 * @param stop whether to actually stop "loading"
 */
groupquiz.quiz_page_loaded = function (stop) {
 //TODO this just needs to shut off the loading box
    if (stop) {

        var controls = document.getElementById('controlbox');
        var instructions = document.getElementById('instructionsbox');
        var loadingbox = document.getElementById('loadingbox');

        // initialize ajax object
        groupquiz.ajax.init();


        // next insert rtqinitinfo into the groupquizvars
        for (var prop in window.rtqinitinfo) {
            if (rtqinitinfo.hasOwnProperty(prop)) {
                this.set(prop, rtqinitinfo[prop]);
            }
        }

        // set the boxes vars in the relatimequiz.vars for access in other functions
        groupquiz.set('controlbox', controls);
        groupquiz.set('instructionsbox', instructions);
        groupquiz.qcounter = false;
        groupquiz.set('quizinfobox', document.getElementById('quizinfobox'));

        if (controls) {
            controls.classList.remove('hidden');
        }
        instructions.classList.remove('hidden');
        loadingbox.classList.add('hidden');

	// show the timer
	var timerbox = document.getElementById('timerbox');
	if (timerbox) {
	    timerbox.classList.remove('hidden');
	    console.log('orginal timeleft ' + document.getElementById('timeleft').textContent);
	    timeleft = document.getElementById('timeleft').textContent;
	    // start timer
            timeleft = setInterval(groupquiz.updateTimer, 1000);
	}

	// show the questions
	 for (questionid in this.vars.questions) {
	    var questionbox = document.getElementById('q' + questionid + '_container');
	    questionbox.classList.remove('hidden');
	}

        //lastly call the instructor/student's quizinfo function
                groupquiz.getQuizInfo();
        
    } else {
        setTimeout(groupquiz.quiz_page_loaded(true), 1000);
    }
};

var timeleft;

groupquiz.updateTimer = function () {
    --timeleft;

    // timeout
    if (timeleft <= 0) {
        console.log('time has expired');
	timeleft = 0;
    }
    console.log('time remaining ' + timeleft);
    document.getElementById('timeleft').innerHTML = timeleft;
};

groupquiz.getQuizInfo = function () {
    var params = {
        'sesskey': groupquiz.get('sesskey'),
        'groupquizid': groupquiz.get('groupquizid'),
        'attemptid': groupquiz.get('attemptid'),
        'id': groupquiz.get('id'),
    };

    groupquiz.ajax.create_request('/mod/groupquiz/quizinfo.php', params, function (status, response) {
        if (status == 500) {
            console.log('There was an error....' + response.message);
	    if (response.message == 'attemptclosed') {
		// redirect to viewquizattempt
		var newparams = 'viewquizattempt.php?attemptid=' + groupquiz.get('attemptid') + '&id=' + groupquiz.get('id') + '&quizid=' + groupquiz.get('groupquizid');
		var newurl = window.location.href.replace(/view.php$/, newparams);
		window.location.replace(newurl);
	    } else {
                window.location.replace(window.location.href + "?id=" + groupquiz.get('id'));
            }
        } else if (status == 200) {
            const questions = JSON.parse(response.status);

            timeleft = JSON.parse(response.timeleft);
	    if (timeleft) {
                console.log('received new time remaining from server ' + timeleft);
	    }
            //update timer
            if (timeleft > 0) {
                var timerbox = document.getElementById('timerbox');
		if (timerbox) {
                    timerbox.classList.remove('hidden');
		}
            }
                
            // loop
            questions.forEach(function(question) {
		var responsebox = document.getElementById('q' + question.qnum + '_container');
		// only update question html if new
                var sequencecheck = document.getElementsByName(question.seqcheckname);
                var field = sequencecheck[0];
		if (question.seqcheckval > field.value) {
		    var replace = '<div class="groupquizbox" id="q' + question.qnum + '_container">';
		    var html = question.html.replace(replace, "");
		    html = html.replace(/<\/div>$/, "");
		    html = html.replace(/groupquizbox hidden/, "/groupquizbox");
		    console.log('replacing html for q' + question.qnum);
                    responsebox.innerHTML = html;
		}
	    })
        }


        var time = 3000 + Math.floor(Math.random() * (100 + 100) - 100);
        setTimeout(groupquiz.getQuizInfo, time);
    });
};
            
