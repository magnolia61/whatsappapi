<h3>{$ruleActionHeader}</h3>
<div class="crm-block crm-form-block crm-civirule-rule_action-block-whatsapp-send">
  <div class="help-block" id="help">
    {ts}<p>The message is built from the selected message template, so tokens work as they do in e-mail. Note that only fully qualified tokens are replaced, for example <code>&#123;contact.first_name&#125;</code> — a short form like <code>&#123;first_name&#125;</code> stays in the text as-is.</p>
    <p><strong>Message type.</strong> Free-form text is only accepted by WhatsApp within 24 hours of the contact's own last message. A rule usually fires outside that window, so pick <em>Template</em> and register the provider-side approved template (at Twilio: a Content Template, whose SID goes in the Namespace field of the WhatsApp template).</p>
    <p>If your rule is triggered by someone who is not logged in — a scheduled job, an inbound message, a public form — set the Message sender below, otherwise there is no sender to record.</p>
    {/ts}
  </div>
  <div class="crm-section">
    <div class="label">{$form.provider_id.label}</div>
    <div class="content">{$form.provider_id.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.template_id.label}</div>
    <div class="content">{$form.template_id.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.type.label}</div>
    <div class="content">{$form.type.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.from_contact_id.label}</div>
    <div class="content">{$form.from_contact_id.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.alternative_receiver.label}</div>
    <div class="content">{$form.alternative_receiver.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section hiddenElement alternative_receiver_phone_number">
    <div class="label">{$form.alternative_receiver_phone_number.label}</div>
    <div class="content">{$form.alternative_receiver_phone_number.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.smarty.label}</div>
    <div class="content">{$form.smarty.html}</div>
    <div class="clear"></div>
  </div>
</div>
<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>

{literal}
  <script type="text/javascript">
    cj(function() {
      cj('#alternative_receiver').change(triggerAlternativeReceiverChange);

      triggerAlternativeReceiverChange();
    });

    function triggerAlternativeReceiverChange() {
      cj('.crm-section.alternative_receiver_phone_number').addClass('hiddenElement');
      var val = cj('#alternative_receiver').prop('checked');
      if (val) {
        cj('.crm-section.alternative_receiver_phone_number').removeClass('hiddenElement');
      }
    }
  </script>
{/literal}
