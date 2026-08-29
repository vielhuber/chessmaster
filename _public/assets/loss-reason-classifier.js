export default class LossReasonClassifier {
    static MINIMUM_DECISIVE_LOSS = 250;
    static MINIMUM_PLAYABLE_SCORE = -150;
    static MAXIMUM_LOST_SCORE = -250;

    static isBlunder(beforeScore, afterOpponentScore) {
        let afterScore = -afterOpponentScore;
        let loss = Math.max(0, beforeScore - afterScore);

        return (
            beforeScore >= LossReasonClassifier.MINIMUM_PLAYABLE_SCORE &&
            afterScore <= LossReasonClassifier.MAXIMUM_LOST_SCORE &&
            loss >= LossReasonClassifier.MINIMUM_DECISIVE_LOSS
        );
    }
}
