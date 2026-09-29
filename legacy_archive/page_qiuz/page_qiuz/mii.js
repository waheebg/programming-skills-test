 // Select Elements     //استدعاء العناصر
 let countSpan = document.querySelector(".count span");
 let bullets = document.querySelector(".bullets");
 //category
 let bulletsSpanContainer = document.querySelector(".bullets .spans");
 let quizArea = document.querySelector(".quiz-area");
 let answersArea = document.querySelector(".answers-area");
 let submitButton = document.querySelector(".submit-button");
 let resultsContainer = document.querySelector(".results");
 let countdownElement = document.querySelector(".countdown");
 let category= document.querySelector(".category");
 // Set Options    
 let currentIndex = 0;
 let rightAnswers = 0;
 let countdownInterval;
 let randnum = 0;
 
 function getQuestions() {   // الحصول على الاسئلة
   let myRequest = new XMLHttpRequest();
   myRequest.onreadystatechange = function () {
     if (this.readyState === 4 && this.status === 200) {
       //let questionsObject = JSON.stringify(this.responseText);
        let questionsObject = JSON.parse(this.responseText);
       let qCount = questionsObject.length;
 
       // Create Bullets + Set Questions Count
       createBullets(qCount);
 
       // Add Question Data
       addQuestionData(questionsObject[currentIndex], qCount);
 
       // Start CountDown
       countdown(15, qCount);
 
       // Click On Submit
       submitButton.onclick = () => {
         // Get Right Answer
         let theRightAnswer = questionsObject[currentIndex].right_answer;
 
         // Increase Index
         currentIndex++;
        
         // Check The Answer
         checkAnswer(theRightAnswer, qCount);
 
         // Remove Previous Question   // ازالة السوال السابق
         quizArea.innerHTML = "";
         answersArea.innerHTML = "";
         
         // Add Question Data
         addQuestionData(questionsObject[currentIndex], qCount);
       
         // Handle Bullets Class
         handleBullets();
 
         // Start CountDown
         clearInterval(countdownInterval);
         countdown(15, qCount); 
 
         // Show Results
         showResults(qCount);
  // randnum = Math.random(1)*1*9;
        //randnum = getRandom(1, qCount);
 
         console.log(randnum);
 
       };
     }
   };
  
   let url ="dataw.php";
   myRequest.open("GET",url, true);
   myRequest.send();
 }
 
 getQuestions();
 
 
  // الحصول على اسيلة عشواية
  
   
  
   
 
 function createBullets(num) {
   countSpan.innerHTML = num;
 
   // Create Spans
   for (let i = 0; i < num; i++) {
     // Create Bullet
     let theBullet = document.createElement("span");
 
     // Check If Its First Span
     if (i === 0) {
       theBullet.className = "on";
     }
 
     // Append Bullets To Main Bullet Container
     bulletsSpanContainer.appendChild(theBullet);
   }
 }
 
 function addQuestionData(obj, count) {
   if (currentIndex < count) {
     // Create H2 Question Title
     let questionTitle = document.createElement("h2");
    
     
     // Create Question Text
     let questionText = document.createTextNode(obj["title"]);
 
     // Append Text To H2
     questionTitle.appendChild(questionText);
 
     // Append The H2 To The Quiz Area
     quizArea.appendChild(questionTitle);
 
     // Create The Answers
     for (let i = 1; i <= 4; i++) {
       // Create Main Answer Div
       let mainDiv = document.createElement("div");
 
       // Add Class To Main Div
       mainDiv.className = "answer";
 
       // Create Radio Input
       let radioInput = document.createElement("input");
 
       // Add Type + Name + Id + Data-Attribute
       radioInput.name = "question";
       radioInput.type = "radio";
       radioInput.id = `answer_${i}`;
       radioInput.dataset.answer = obj[`answer_${i}`];
 
       // Make First Option Selected
       if (i === 1) {
         radioInput.checked = true;
       }
 
       // Create Label
       let theLabel = document.createElement("label");
 
       // Add For Attribute
       theLabel.htmlFor = `answer_${i}`;
 
       // Create Label Text 
       let theLabelText = document.createTextNode(obj[`answer_${i}`]);
 
       // Add The Text To Label
       theLabel.appendChild(theLabelText);
 
       // Add Input + Label To Main Div
       mainDiv.appendChild(radioInput);
       mainDiv.appendChild(theLabel);
 
       // Append All Divs To Answers Area
       answersArea.appendChild(mainDiv);
     }
   }
 }
 
 function checkAnswer(rAnswer, count) {
   let answers = document.getElementsByName("question");
   let theChoosenAnswer;
 
   for (let i = 0; i < answers.length; i++) {
     if (answers[i].checked) {
       theChoosenAnswer = answers[i].dataset.answer;
     }
   }
 
   if (rAnswer === theChoosenAnswer) {
     rightAnswers++;
   }
 }
 
 function handleBullets() {
   let bulletsSpans = document.querySelectorAll(".bullets .spans span");
   let arrayOfSpans = Array.from(bulletsSpans);
   arrayOfSpans.forEach((span, index) => {
     if (currentIndex === index) {
       span.className = "on";
     }
   });
 }
 
 function showResults(count) { // عند انتاء الاسئة 
   let theResults;
   
   if (currentIndex === count) {
     quizArea.remove();  //لاخفاء العناصر بعد انتهاء الاسيلة 
     answersArea.remove();
     submitButton.remove();
     bullets.remove();
 
     if (rightAnswers > count / 2 && rightAnswers < count) {
       theResults = `<span class="good">جيد</span><center>${rightAnswers} من ${count}</center> النتيجة<br>
       <br>
        <div> <form method='POST' action='result.php'>
        <input type='hidden' name='rightAnswers' value='${rightAnswers}'>
        <input type='hidden' name='rightAnswers' value='${count}'>
        <center><input type="submit" value="طباعة"></center>
        
        </form></div>`;

     } else if (rightAnswers === count) {
       theResults = `<span class="perfect">مثالي</span>
        اقد اكملت جميع الاسئلة بكامل<br><center>النتيجة</center><br>
        <center>${rightAnswers} من ${count}</center>
         <br>
        <div> <form method='POST' action='result.php'>
        <input type='hidden' name='rightAnswers' value='${rightAnswers}'>
        <input type='hidden' name='rightAnswers' value='${count}'>
        <center><input type="submit" value="طباعة"></center>
        
        </form></div>
        `;

         /*theResults =`<div> <form method='POST' action='result.php'>
        <input type='hidden' name='rightAnswers' value='${rightAnswers}'>
        <input type='hidden' name='rightAnswers' value='${count}'>
        <input type="submit" value="طباعة">
        </form></div>`;*/
     } else {
       theResults = `<span class="bad">سيا</span><center>النتيجة</center><br>
       <center>${rightAnswers} من ${count}</center>
        <br>
       <div> <form method='POST' action='result.php'>
       <input type='hidden' name='rightAnswers' value='${rightAnswers}'>
       <input type='hidden' name='rightAnswers' value='${count}'>
       <center><input type="submit" value="طباعة"></center>
       </form></div>
       <br>
       <meta http-equiv="refresh" content="3; url=http://localhost/php/home_quiz/">
       `;
     }
    
 
     resultsContainer.innerHTML = theResults;
     resultsContainer.style.padding = "10px";
     resultsContainer.style.backgroundColor = "white";
     resultsContainer.style.marginTop = "10px";
     
   }
 }
 
 function countdown(duration, count) { //دالة العد التنازلي 
   if (currentIndex < count) {
     let minutes, seconds;
     countdownInterval = setInterval(function () {
       minutes = parseInt(duration / 60);
       seconds = parseInt(duration % 60);
 
       minutes = minutes < 10 ? `0${minutes}` : minutes;
       seconds = seconds < 10 ? `0${seconds}` : seconds;
 
       countdownElement.innerHTML = `${minutes}:${seconds}`; // لعرض الثواني والدقايق
 
       if (--duration < 0) {  
         clearInterval(countdownInterval);  // توقف الوقت عند النتقال الى السوال الثاني
         submitButton.click();
       }
     }, 1000);
   }
 }
  function getRandom(min,max) {
   let x = Math.floor(Math.random() * (max - min + 1) + min);
  
    return x;
  }
 
  