<?php
class Invoice extends Document {

    public $contact_type = 'Partner';
    public $j_invoice;

    public $type;

    function init()
    {
        parent::init();
        $this->j_invoice = $this->join('invoice.document_id');
        $this->j_invoice->hasOne('partner_id', new $this->contact_type())
            ->addTitle();
        $this->getElement('partner_id')->required = true;

        $l =$this->hasMany('Lines', new Line());
        $l->addField('total', ['aggregate'=>'sum','type'=>'money']);
        $l->addField('net', ['aggregate'=>'sum','type'=>'money']);

        $this->j_invoice->addField('is_paid', ['type'=>'boolean']);

        if ($this->type) {
            $this->addCondition('type', $this->type);
        }
    }

    function makePosted()
    {
        $this->atomic(function() {
            $effect = $this->type == 'sale' ? -1 : 1;

            if ($this['total'] == 0) {
                throw new \atk4\data\ValidationException(['total'=>'This invoice is empty.']);
            }



            foreach($this->ref('Lines') as $line) {

                // Create stack/effect model
                $stock = $line->ref('article_id')->ref('Stock')->addCondition('type', 'effect');

                $stock['qty_increase'] = $line['qty'] * $effect;
                $stock['date'] = new \DateTime();
                $stock['description'] = 'Invoice '.$this['ref'].' now live';
                $stock->reload_after_save = false;
                $stock->save();

                // make relation
                $stock->ref('Related')->save(['document_id'=>$this->id]);
            }

            $this['status'] = 'posted';
            $this->save();
        });
    }

    function pay() {
        $this['is_paid'] = true;
        $this->saveAndUnload();
    }
}
