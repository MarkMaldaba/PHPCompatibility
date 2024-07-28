<?php
/**
 * PHPCompatibility, an external standard for PHP_CodeSniffer.
 *
 * @package   PHPCompatibility
 * @copyright 2012-2020 PHPCompatibility Contributors
 * @license   https://opensource.org/licenses/LGPL-3.0 LGPL3
 * @link      https://github.com/PHPCompatibility/PHPCompatibility
 */

namespace PHPCompatibility\Sniffs\Attributes;

use PHPCompatibility\Helpers\ScannedCode;
use PHPCompatibility\Sniff;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;
use PHPCSUtils\Utils\GetTokensAsString;

/**
 * Attributes as a form of structured, syntactic metadata to declarations of classes, properties,
 * functions, methods, parameters and constants is supported as of PHP 8.0.
 *
 * {@internal This sniff does not check whether attributes are used correctly and in
 * combination with syntaxes for which attributes are valid.
 * If that's not the case, PHP 8.0 would throw a parse error anyway.}
 *
 * PHP version 8.0
 *
 * @link https://wiki.php.net/rfc/attributes_v2
 * @link https://wiki.php.net/rfc/attribute_amendments
 * @link https://wiki.php.net/rfc/shorter_attribute_syntax
 * @link https://wiki.php.net/rfc/shorter_attribute_syntax_change
 * @link https://www.php.net/manual/en/language.attributes.php
 *
 * @since 10.0.0
 */
class NewAttributesSniff extends Sniff
{

    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @since 10.0.0
     *
     * @return array<int|string>
     */
    public function register()
    {
        return [\T_ATTRIBUTE];
    }

    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @since 10.0.0
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        if (ScannedCode::shouldRunOnOrBelow('7.4') === false) {
            return;
        }

        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$stackPtr]['attribute_closer']) === false) {
            // Live coding or parse error. Shouldn't be possible as shouldn't have retokenized in that case.
            return; // @codeCoverageIgnore
        }

        $opener = $stackPtr;
        $closer = $tokens[$stackPtr]['attribute_closer'];

        /*
         * Check if the attribute is cross-version compatible with PHP < 8.0.
         */
        if ($tokens[$opener]['line'] !== $tokens[$closer]['line']) {
            $phpcsFile->addError(
                'Multi-line attributes will result in a parse error in PHP 7.4 and earlier.',
                $opener,
                'FoundMultiLine'
            );
        }

        $nextAfter = $phpcsFile->findNext(Tokens::$emptyTokens, ($closer + 1), null, true);
        if ($nextAfter !== false
            && $tokens[$nextAfter]['code'] !== \T_ATTRIBUTE
            && $tokens[$closer]['line'] === $tokens[$nextAfter]['line']
        ) {
            $phpcsFile->addError(
                'Code after an inline attribute on the same line will be ignored in PHP 7.4 and earlier and is likely to cause a parse error or functional error.',
                $closer,
                'FoundInline'
            );
        }

        $textPtr = $opener;
        while (($textPtr = $phpcsFile->findNext(\T_CONSTANT_ENCAPSED_STRING, ($textPtr + 1), $closer)) !== false) {
            if ($tokens[$textPtr]['line'] !== $tokens[$opener]['line']) {
                // We only need to examine text strings on the same line as the opener.
                break;
            }

            if (\strpos($tokens[$textPtr]['content'], '?>') !== false) {
                $phpcsFile->addError(
                    'Text string containing text which looks like a PHP close tag found on the same line as an attribute opener. This will cause PHP to switch to inline HTML in PHP 7.4 and earlier, which may lead to code exposure and will break functionality. Found: %s',
                    $textPtr,
                    'FoundCloseTag',
                    [$tokens[$textPtr]['content']]
                );
            }
        }

        $phpcsFile->addError(
            'Attributes are not supported in PHP 7.4 or earlier. Found: %s',
            $opener,
            'Found',
            [GetTokensAsString::compact($phpcsFile, $opener, $closer, true)]
        );
    }
}
