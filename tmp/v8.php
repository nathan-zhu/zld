<?php
$javaScriptCode = '
function FizzBuzz(correspondences) {
    this.correspondences = correspondences;
    this.accept = function (number) {
        var result = ""
        for (var divisor in this.correspondences) {
            if (number % divisor == 0) {
                result = result + this.correspondences[divisor];
            }
        }
        if (result) {
            return result;
        } else {
            return number;
        }
    }
}
var myFizzBuzz = new FizzBuzz({3 : "Fizz", 5 : "Buzz"});

"{\"15\" : \"" + myFizzBuzz.accept(15) + "\", \"5\" : \"" + myFizzBuzz.accept(5) + "\"}";
';
$v8 = new V8Js();
$result = $v8->executeString($javaScriptCode);
var_dump($result);
var_dump(json_decode($result));
