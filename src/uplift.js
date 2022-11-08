var questions = [];

$(document).ready(function () {

    // Add listeners to the buttons.
    $(document).on('click', '#add-question', addQuestion);
    $(document).on('click', '.remove-question', removeQuestion);
    $('.remove-question').click(removeQuestion);

    $(document).on('click', '#save-quiz', saveQuiz);
    $(document).on('click', '#reset-quiz', function () { populateQuiz(questions) });
    $(document).on('click', '#check-quiz', checkQuiz);

    // loadQuizData();
});

/**
 * Load any saved questions and update the interface.
 */
function loadQuizData() {
    // If there are saved questions...
    if (localStorage.getItem('questions') !== null) {
        // Load the question.
        questions = JSON.parse(localStorage.getItem('questions'));
        console.log(questions, typeof questions);

        // Update the quiz interface.
        populateQuiz(questions);

        // Update the quiz admin interface.
        populateQuizAdmin(questions);
    }
}

/**
 * Saves the quiz questions from the admin panel, updates the quiz panel.
 */
function saveQuiz() {
    // Extract all of the questions and answers.
    questions = []; // Resets the questions.
    $('#quiz-admin-questions .question').each(function (i, elm) {
        var $row = $(elm).parents('tr');
        var question = $(elm).val();
        var answer = $row.find('.answer').val();

        // Skip the template question.
        if (!$row.hasClass('hidden')) {
            questions.push({ question: question, answer: answer });
        }
    });

    // Save quiz.
    localStorage.setItem('questions', JSON.stringify(questions));

    // // Update quiz panel.
    // populateQuiz(questions);
    // TODO: send data to quiz.php
}

/**
 * Re-populates the quiz with the given questions.
 * 
 * @param questions A list of question/answer pairs (each item is an object
 *                  with the fields 'question' and 'answer').
 */
function populateQuiz(questions) {
    var $quiz = $('#quiz')
    $quiz.html('');
    $('#score').html('');

    for (var i = 0; i < questions.length; i++) {
        $quiz.append(`<li data-id="${i}">${questions[i].question}<br/>` +
            '<textarea rows="3" class="response"></textarea></li>');
    }
}

/**
 * Populates the quiz admin table with the given questions.
 * 
 * @param questions A list of question/answer pairs (each item is an object
 *                  with the fields 'question' and 'answer').
 */
function populateQuizAdmin(question) {
    var $quizAdminTable = $('#quiz-admin-questions')
    for (var i = 0; i < questions.length; i++) {
        var $newRow = $('#question-admin-template').clone();
        $newRow.attr('id', '');
        $newRow.removeClass('hidden');
        // set the value of the column in newRow 
        // that has the class "question" to the
        // text of the current question we're
        // iterating over.
        $newRow.find('.question').val(questions[i].question);
        // questions[i]['question']
        $newRow.find('.answer').val(questions[i].answer);
        $quizAdminTable.append($newRow);
    }
}

/**
 * Adds a new row to the quiz admin question editor table.
 */
function addQuestion() {
    // var newRow = '<tr><td><textarea rows="2" class="question"></textarea></td>'+
    //     '<td><textarea rows="2" class="answer"></textarea></td>'+
    //     '<td><button class="remove-question">Delete</button></td></tr>';
    var newRow = $('#question-admin-template').clone();
    newRow.attr('id', '');
    newRow.removeClass('hidden');
    $('#quiz-admin-questions').append(newRow);
}

/**
 * Removes a new row to the quiz admin question editor table. It is assumed that
 * this is called with the context (this) of the specific "remove" button that
 * was clicked.
 */
function removeQuestion() {
    $(this).parents('tr').remove();
}

/**
 * Checks each of the answers in the quiz and marks them as correct/incorrect.
 * Also tallies up a score and records it.
 */
function checkQuiz() {
    var responses = [];
    $('#quiz .response').each(function (i, elm) {
        var $questionItem = $(elm).parents('li');
        var response = $(elm).val();
        var questionId = parseInt($questionItem.data('id'));
        responses.push({
            id: questionId,
            answer: response
        });
    });

    // var $form = $('#response-submission-form');
    // $form.find('[name=responses]').val(JSON.stringify(responses));
    // $form.submit();

    $.ajax('grade.php', {
        method: 'post',
        data: { responses: JSON.stringify(responses) },
        dataType: 'json',
        success: function (data, textStatus, jqXHR) {
            // data = JSON.parse(data);
            console.log(data, typeof (data));
            gradeQuiz(data);

        },
        error: function (jqXHR, textStatus, errorThrown) {
            alert(`There was an error with your request! ${textStatus}: ${errorThrown}`);
        }
    });
}

function gradeQuiz(questions) {
    var correct = 0;
    $('#quiz .response').each(function (i, elm) {
        var $questionItem = $(elm).parents('li');
        var questionIndex = parseInt($questionItem.data('id'));
        if (questions[questionIndex].correct) {
            correct++;
            $questionItem.addClass('correct');
            $questionItem.removeClass('incorrect');
        } else {
            $questionItem.addClass('incorrect');
            $questionItem.removeClass('correct');
        }
    });
    $('#score').html(`Score: ${correct}/${questions.length} = ${correct / questions.length}`);
}