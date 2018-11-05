<?php
/**
 * Created by PhpStorm.
 * User: damilare
 * Date: 02/11/2018
 * Time: 9:31 AM
 */

namespace Damilare\PerfectTenseClient;

class PTInteractiveEditor {

    private $ptClient;
    private $data;
    private $apiKey;
    private $ignoreNoReplacement;
    private $flattenedTransformations;
    private $transformStack;
    private $transStackSize;
    private $allAvailableTransforms;


    /**
     * Constructor for the interactive editor.
     *
     * @param object $arguments                                 And array containing the below parameters
     * @param object $arguments->ptClient                       An instance of the PTClient object (generally, only 1 is ever created)
     * @param object $arguments->data                           The result object returned from PTClient->submitJob
     * @param string $arguments->apiKey                         The user's API key (they must be prompted in some way to enter this.
     *                                                              It can be found here: https://app.perfecttense.com/home)
     * @param boolean $arguments->ignoreNoReplacement=false     Optionally ignore transformations that do not offer replacement text
     */
    public function __construct($arguments) {

        $this->ptClient = $arguments['ptClient'];
        $this->data = $arguments['data'];
        $this->apiKey = $arguments['apiKey'];
        $this->ignoreNoReplacement = !array_key_exists('ignoreNoReplacement', $arguments) ? False : $arguments['ignoreNoReplacement'];

        // Jobs must be submitted with the 'rulesApplied' response type to use editor. This is on by default.
        if (!array_key_exists('rulesApplied', $this->data)) {
            die("Must include rulesApplied response type to use interactive editor.");
        }

        // All functions assume that this metadata has been set when interacting with corrections
        if (!array_key_exists('hasMeta', $this->data) || !$this->data['hasMeta']) {
            $this->ptClient->setMetaData($this->data);
        }

        // Transformations from each sentence flattened into one array for easier indexing
        $this->flattenedTransformations = array();


        for ($sentIndex = 0; $sentIndex < count($this->data['rulesApplied']); $sentIndex++) {
            $sentence =& $this->data['rulesApplied'][$sentIndex];

            for ($transIndex = 0; $transIndex < count($sentence['transformations']); $transIndex++) {
                $this->flattenedTransformations[] =& $sentence['transformations'][$transIndex];
            }
        }

        // Stack tracking accepted/rejected transformations
        $this->transformStack = array_filter($this->flattenedTransformations, function ($transform) {
            return !$this->ptClient->isClean($transform);
        });

        $this->transStackSize = count($this->transformStack);

        // Cache of available transformations in current state
        $this->allAvailableTransforms = null;

        $this->updateAvailableCache();
    }

    /**
     * Execute (accept) all transformations found for this input.
     *
     * @param boolean $skipSuggestions=false      Optionally skip any transformations that are just suggestions
     */
    public function applyAll($skipSuggestions = false) {
        while ($this->hasNextTransform($skipSuggestions)) {
            $this->acceptCorrection($this->getNextTransform($skipSuggestions));
        }
    }

    /**
     * Undo all actions taken (accepting/rejecting transformations)
     */
    public function undoAll() {
        while ($this->canUndoLastTransform()) {
            $this->undoLastTransform();
        }
    }

    /**
     * Get the assigned grammar score for this input text.
     *
     * This is a value from 0 to 100, 0 being the lowest possible score.
     *
     * @return number        The assigned grammar score
     */
    public function getGrammarScore() {
        return $this->ptClient->getGrammarScore($this->data);
    }

    /**
     * Get usage statistics for the current user
     *
     * @return object   Returns an object containing the user's usage statistics. See our API documentation for more: https://www.perfecttense.com/docs/
     */
    public function getUsage() {
        return $this->ptClient->getUsage($this->apiKey);
    }

    /**
     * Accessor for the result that this interactive editor is wrapping
     *
     * @return object   The result from PTClient->submitJob
     */
    public function getData() {
        return $this->data;
    }

    /**
     * Get the transformation at the specified 0-based index.
     *
     * This index is relative to the "flattened" list of all transformations found accross all sentences.
     *
     * @return object   The transformation object at the specified index, or null if the index is invalid
     */
    public function getTransform($flattenedIndex) {
        return $this->flattenedTransformations[$flattenedIndex];
    }

    /**
     * Get the sentence at the specified 0-based index.
     *
     * Sentences are indexed based on their order in the original text.
     *
     * ex. "This is sentence 1. This is sentence 2."
     * intEditor->getSentence(1) // returns sentence object for "This is sentence 2."
     *
     * @return object   The sentence object at the specified index, or null if the index is invalid
     */
    public function getSentence($sentenceIndex) {
        return $this->ptClient->getSentence($this->data, $sentenceIndex);
    }

    /**
     * Get the sentence object that contains the parameter transformation.
     *
     * @return object   The sentence object containing the parameter transformation
     */
    public function getSentenceFromTransform($transform) {
        return $this->getSentence($transform['sentenceIndex']);
    }

    /**
     * Get a list of all currently available transformations.
     *
     * @return object   A list of all currently available transformations.
     */
    public function getAvailableTransforms() {
        return $this->allAvailableTransforms;
    }

    /**
     * Private utility to get the next available transformation that is not a suggestion.
     *
     * To accomplish the same thing publicly, use getNextTransform($ignoreSuggestions=true)
     *
     * @return object   The next available transformation that is not a suggestion (or null if none)
     */
    private function getNextNonSuggestion() {
        for ($i = 0; $i < count($this->allAvailableTransforms); $i++) {
            if (!$this->ptClient->isSuggestion($this->allAvailableTransforms[$i])) {
                return $this->allAvailableTransforms[$i];
            }
        }
    }

    /**
     * Returns true if there exists an available transformation.
     *
     * This is a utility for iterating through available transformations:
     *
     * while (intEditor->hasNextTransform()) {
     *    $nextTransform = intEditor->getNextTransform();
     * }
     *
     * @param boolean $ignoreSuggestions=false    Optionally ignore transformations that are suggestions
     * @return boolean                            True if there is an available transform, else false
     */
    public function hasNextTransform($ignoreSuggestions = false) {
        if ($ignoreSuggestions) {
            return $this->getNextNonSuggestion() != null;
        } else {
            return count($this->allAvailableTransforms) > 0;
        }
    }

    /**
     * Get the next available transformation
     *
     * This is a utility for iterating through available transformations:
     *
     * while (intEditor->hasNextTransform()) {
     *    $nextTransform = intEditor->getNextTransform();
     * }
     *
     * @param boolean $ignoreSuggestions=false    Optionally ignore transformations that are suggestions
     * @return object                             A transformation object if available, else null
     */
    public function getNextTransform($ignoreSuggestions = false) {
        if ($ignoreSuggestions) {
            return $this->getNextNonSuggestion();
        } else {
            return $this->allAvailableTransforms[0];
        }
    }

    /**
     * Get a list of all transformations that affect the EXACT SAME text as the parameter transformation.
     *
     * Ex. "He hould do it"
     *
     * Two transformations will be created:
     *   "hould" -> "could"
     *   "hould" -> "would"
     *
     * $overlapping = $intEditor-getOverlappingTransforms(["hould" -> "could"])
     *      This returns array("hould" -> "could", "hould" -> "would")
     *
     * @param object $transform         The transform that you are looking for overlaps of
     * @return object                   An array of overlapping transformations (minimum an array of 1 element - the parameter transform itself)
     */
    public function getOverlappingTransforms($transform) {
        $sentence = $this->getSentenceFromTransform($transform);
        $overlappingTransforms = $this->ptClient->getOverlappingGroup($sentence, $transform);

        return array_filter($overlappingTransforms, function ($t) {
            return $this->ptClient->affectsSameTokens($t, $transform);
        });
    }

    /**
     * Returns the "current" text, considering transformations that have been accepted or rejected.
     *
     * When the interactive editor is first created, this will return the original text submitted to
     * Perfect Tense since no actions have been taken. Once transformations are accepted, the return
     * from this function will change accordingly.
     *
     * @return string        The current text, considering applied transformations
     */
    public function getCurrentText() {
        return $this->ptClient->getCurrentText($this->data);
    }

    /**
     * Accept the parameter transformation
     *
     * Mainly, this will replace the "affected" string with the "added" string, as well as update state
     * information to track how this might affect the availability of other transformations.
     *
     *
     * @param boolean $transform       The transformation to accept
     * @return boolean                 True if the transformation was successfully accepted, else false (it may not be available)
     */
    public function acceptCorrection($transform) {
        if ($this->ptClient->acceptCorrection($this->data, $transform, $this->apiKey)) {
            $this->updateTransformRefs($transform);
            $this->updateAvailableCache();
            $this->transformStack[] = $transform;
            $this->transStackSize++;

            return True;
        }

        return False;
    }

    /**
     * Reject the parameter transformation, and update state information accordingly
     *
     * @param boolean $transform       The transformation to reject
     * @return boolean                 True if the transformation was successfully rejected, else false (it may not be available)
     */
    public function rejectCorrection($transform) {

        if ($this->ptClient->rejectCorrection($this->data, $transform, $this->apiKey)) {
            $this->updateTransformRefs($transform);
            $this->updateAvailableCache();
            $this->transformStack[] = $transform;
            $this->transStackSize++;

            return True;
        }

        return False;
    }

    /**
     * Undo the last accept or reject action (pop off of the stack), and update state information
     *
     * @return boolean       True if the last action was successfully undone, else false
     */
    public function undoLastTransform() {

        if ($this->transStackSize > 0) {
            $lastTransform = $this->getLastTransform();

            if ($this->ptClient->resetCorrection($this->data, $lastTransform, $this->apiKey)) {
                $this->updateTransformRefs($lastTransform);
                $this->updateAvailableCache();
                array_pop($this->transformStack);
                $this->transStackSize--;
                return True;
            }
        }

        return False;
    }

    /**
     * Check to see if the parameter transformation can be made (is present in the sentence).
     *
     * A transform "can be made" if its 'affected' tokens are present in the 'activeTokens' of the sentence.
     *
     * If you use the getNextTransform function, you do not need to call this. Use this function
     * if you are operating on transformations in a random order and are unsure if the transform
     * is available.
     *
     * @param boolean $transform       The transformation in question
     * @return boolean                 True if the parameter transformation can be made, else false
     */
    public function canMakeTransform($transform) {
        $sentence = $this->getSentenceFromTransform($transform);
        return $this->ptClient->canMakeTransform($sentence, $transform);
    }

    /**
     * Returns true if the last accept/reject action can be undone.
     *
     * @return boolean       True if the last action can be undone, else false
     */
    public function canUndoLastTransform() {

        if ($this->transStackSize > 0) {
            $lastTransform = $this->getLastTransform();
            $sentence = $this->data['rulesApplied'][$lastTransform['sentenceIndex']];

            return $this->ptClient->canUndoTransform($sentence, $lastTransform);
        }

        return False;
    }

    /**
     * Get the last transformation that was interacted with (accepted or rejected)
     *
     * @return object    The last transformation interacted with, or null if none
     */
    public function getLastTransform() {
        return $this->transformStack[$this->transStackSize - 1];
    }

    /**
     * Get the character offset of the parameter transformation relative to the sentence start
     * in the sentence's current state (considering any modifications through accepting/rejecting
     * transformations)
     *
     * @return number     The character offset of the transform in its sentence
     */
    public function getTransformOffset($transform) {
        return $this->ptClient->getTransformOffset($this->data, $transform);
    }

    /**
     * Get the character offset of the parameter sentence relative to the overall text start
     * in the text's current state (considering any modifications through accepting/rejecting
     * transformations)
     *
     * @return number     The character offset of the sentence
     */
    public function getSentenceOffset($sentence) {
        return $this->ptClient->getSentenceOffset($this->data, $sentence);
    }

    /**
     * Get the character offset of the parameter transformation relative to the overall text start
     * in the text's current state (considering any modifications through accepting/rejecting
     * transformations)
     *
     * This is effectively the same as calling getSentenceOffset + getTransformOffset
     *
     * @return number     The character offset of the transform in the text
     */
    public function getTransformDocumentOffset($transform) {
        $sentence = $this->getSentenceFromTransform($transform);
        return $this->getSentenceOffset($sentence) + $this->getTransformOffset($transform);
    }

    /**
     * Get the tokens affected by this transform as a string
     *
     * @return string     The tokens affected as a string
     */
    public function getAffectedText($transform) {
        return $this->ptClient->getAffectedText($transform);
    }

    /**
     * Get the tokens added by this transform as a string
     *
     * @return string     The tokens added as a string
     */
    public function getAddedText($transform) {
        return $this->ptClient->getAddedText($transform);
    }

    /**
     * Get the original text of this job
     *
     * @return string     The original text of the job
     */
    public function getOriginalText() {
        return $this->ptClient->getOriginalText($this->data);
    }

    /**
     * Get all clean transformations remaining.
     *
     * A transform is "clean" if it has not been accepted or rejected yet.
     *
     * Note that not all "clean" transformations are necessarily available. To check
     * if they are available, call canMakeTransform.
     *
     * @return object     An array of all clean transformations.
     */
    public function getAllClean() {
        return array_filter($this->flattenedTransformations, function ($transform) {
            return $this->ptClient->isClean($transform);
        });
    }

    /**
     * Get the number of sentences in the job
     *
     * @return number     The number of sentences in the job
     */
    public function getNumSentences() {
        return $this->ptClient->getNumSentences($this->data);
    }

    /**
     * Get the number of transformations in the job
     *
     * @return number     The number of transformations in the job
     */
    public function getNumTransformations() {
        return count($this->flattenedTransformations);
    }

    // Updates cache of available transformations (optionally skipping suggestions without replacements)
    private function updateAvailableCache() {
        $this->allAvailableTransforms = array_values(array_filter($this->flattenedTransformations, function ($transform) {
            return $transform['isAvailable'] && (!$this->ignoreNoReplacement || $transform['hasReplacement']);
        }));
    }

    // PHP mutability... update all references to transform
    private function updateTransformRefs(&$transform) {
        $sentence =& $this->data['rulesApplied'][$transform['sentenceIndex']];
        $sentence['transformations'][$transform['indexInSentence']] = $transform;
        $this->flattenedTransformations[$transform['transformIndex']] = $transform;
    }

}
