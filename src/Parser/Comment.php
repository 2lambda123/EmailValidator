<?php

namespace Egulias\EmailValidator\Parser;

use Egulias\EmailValidator\EmailLexer;
use Egulias\EmailValidator\Result\ValidEmail;
use Egulias\EmailValidator\Warning\CFWSNearAt;
use Egulias\EmailValidator\Result\InvalidEmail;
use Egulias\EmailValidator\Parser\CommentStrategy;
use Egulias\EmailValidator\Result\Reason\ExpectingATEXT;
use Egulias\EmailValidator\Result\Reason\UnclosedComment;
use Egulias\EmailValidator\Result\Reason\UnOpenedComment;
use Egulias\EmailValidator\Warning\Comment as WarningComment;

class Comment extends Parser
{
    //change to private when removed from parent parser
    protected $openedParenthesis = 0;
    private $commentStrategy;

    public function __construct(EmailLexer $lexer, CommentStrategy $commentStrategy)
    {
        $this->lexer = $lexer;
        $this->commentStrategy = $commentStrategy;
    }

    public function parse($str)
    {
        if ($this->lexer->token['type'] === EmailLexer::S_OPENPARENTHESIS) {
            $this->openedParenthesis++;
            if($this->noClosingParenthesis()) {
                return new InvalidEmail(new UnclosedComment(), $this->lexer->token['value']);
            }
        }

        if ($this->lexer->token['type'] === EmailLexer::S_CLOSEPARENTHESIS) {
            return new InvalidEmail(new UnOpenedComment(), $this->lexer->token['value']);
        }

        $this->warnings[WarningComment::CODE] = new WarningComment();

        $moreTokens = true;
        while ($this->commentStrategy->exitCondition($this->lexer, $this->openedParenthesis) && $moreTokens){

            if ($this->lexer->isNextToken(EmailLexer::S_OPENPARENTHESIS)) {
                $this->openedParenthesis++;
            }
            $this->warnEscaping();
            if($this->lexer->isNextToken(EmailLexer::S_CLOSEPARENTHESIS)) {
                $this->openedParenthesis--;
            }
            $moreTokens = $this->lexer->moveNext();
        }

        if($this->openedParenthesis >= 1) {
            return new InvalidEmail(new UnclosedComment(), $this->lexer->token['value']);
        } else if ($this->openedParenthesis < 0) {
            return new InvalidEmail(new UnOpenedComment(), $this->lexer->token['value']);
        }

        $finalValidations = $this->commentStrategy->endOfLoopValidations($this->lexer);

        $this->warnings = array_merge($this->warnings, $this->commentStrategy->getWarnings());

        return $finalValidations;
    }

    private function noClosingParenthesis() : bool 
    {
        try {
            $this->lexer->find(EmailLexer::S_CLOSEPARENTHESIS);
            return false;
        } catch (\RuntimeException $e) {
            return true;
        }
    }
}