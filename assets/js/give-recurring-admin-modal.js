/**
 * This class extend GiveWP core GiveModal modal class to create Sync subscription modal.
 *
 * @since 1.11.3
 */
export default class GiveRecurringSyncSubscriptionModal extends Give.modal.GiveModal {
    constructor(obj) {
        obj.type = 'sync-subscription';
        super(obj);
        this.init();
    }

    /**
     * Get template
     *
     * @since 1.11.3
     * @return {string} Template HTML.
     */
    getTemplate() {
        return jQuery('<div>').append(jQuery('#sync-subscription-modal').clone().removeClass('give-hidden')).html();
    }
}
