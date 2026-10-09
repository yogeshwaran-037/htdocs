<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body style="background-color: #111111; color: #FFD700;">
    <center  style= "color:"><h1>
         <?php 
          echo "BIODATA"
          ?>
    </h1></center>
    <h1>home page</h1>
    
    <?php
    function greet(){
        echo "hello,welcom to my website";
        echo "<br>";
        echo"this is a simple php function";
    }

    greet();
    echo "<br>";
    greet();
    echo "<br>";
    function hello($name){
        echo "hello $name";
    }
    $name="yogesh";
    hello($name);
    echo "<br>";
    hello("sahfil");
    echo "<br>";
    hello("shafil");

echo "<br>";
    // function cal($a,$b){
    //     $sum=$a+$b;
    //     $sub=$a-$b;
    //     $mul=$a*$b;
    //     $div=$a%$b;

    //     echo $sum;
    //     echo "<br>";
    //     echo $sub;
    //     echo "<br>";
    //     echo $mul;
    //     echo "<br>";
    //     echo $div;
    // }
    // cal(2,2);
function sum($a,$b){
    return $a+$b;
}
$added_value=sum(3,2);
echo $added_value;
echo "<br>";
function recursive ($n){
    if($n<=5){
        echo $n, "<br>";
        recursive($n+1);
    }
} 
recursive(1);
$welcome = function(){
    echo "<b>HI EVERYONE </b>";
};
$welcome();
 echo "<br>";
$age=16;
 if ($age<=18){
    echo " <h1> not eligible to vote </h1>";
 }
 else{
    echo "eligible to vote";
 }
 echo"<br>";
 echo"<h3>grade</h3>";
 $mark=30;
//  if ($mark>=40){
//        if ($mark>=90){
//         echo"grade A";
//        }
//  elseif($mark>=70){
//     echo "gradeB"; 
//  }
//  elseif($mark>=40){
//     echo "grade c";
//  }
//  else{
//     echo "grade D";
//  }
//  } else {
    
//    echo "<h5 style='color:red;'>re-exam</h5>";
//  }
// $z=6;
// do{
//     echo "hello". "<br>";
//     $z++;
// }while($z<=5)

 echo "<h3> divisible number</h3>";
$number=15;
if ($number / 5 & 3){
    echo "divide by the both number";
}
else if ($number/ 5){
    echo "divide by 5";
}
elseif($number/3){
    echo "divide by 3";
}
else {
   echo"not divide by the number";
}
echo "<br>";
echo"<h3>odd or even</h3>";
$num=10;
if ($num%2==0){
    echo "even number";
}
else {
    echo "odd number";
}
echo "<br>";
$student=["vajar",22,95.4,true];
print_r($student);
echo "<br>";
echo $student[0];
echo "<br>";
print_r($student[4]);
foreach($student as $value){
    echo "<br>";
    echo $value;
}

echo "<br>";
echo '<a href="https://www.youtube.com/">YouTube</a>';

    //echo " yogeshwaran";
    //echo "<br>";
    //echo "CSBS 3rd year";
    //echo"<br>";
    //$name="yogesh";
    //$$name=" the hero ";
    //echo $name." ".$$name;
    //$age=20;
    //echo "<br>";
    //echo "my name is $name and my age is $age";
    //echo "<br>";
    //var_dump($name);
    //echo gettype($name);
    //echo"<br>";
    //$a = 'hello';
    //$$a='world';
    //echo $a." ".$hello

    ''
    <br>
    <a href="https://www.youtube.com/">youtube</a>
    <br>
    <a href="https://chatgpt.com/c/6a9f9575-8050-83ee-a606-0299242ba92d">chatgpt</a>
    <center><h1 style="color: blue;">thankyou</h1></center>
    <img src="spider man.jpg"  alt="spider">
   

</body>
</html>