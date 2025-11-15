const mailchimpFactory = require('@mailchimp/mailchimp_transactional');

// IP limited key for updating templates only
const mailchimp = mailchimpFactory('md-1nbz5-oklI1bd6fN8nxwUQ');

function fixTemplate({ name, code }) {
    let previewText = /\*\|MC_PREVIEW_TEXT\|\*/ig; // regexr.com/69390
    code = code.replace(previewText, '');

    replaceH1Styles = /<h1 style="font-family: Raleway, sans-serif; text-align: center;"><span style="font-size:28px"><span style="color:#6dc4bc"><span style="font-family:helvetica neue,helvetica,arial,verdana,sans-serif">(.*?)<\/span><\/span><\/span><\/h1>/;
    let replaceHeading = `<h1 style="text-align: center;"><font color="#6dc4bc" face="helvetica neue, helvetica, arial, verdana, sans-serif"><span style="font-size:28px">$1</span></font></h1>`;
    code = code.replace(replaceH1Styles, replaceHeading);

    brokenTd = `<td valign="top" class="mcnTextContent" style="padding: 0px 18px 9px;color: #6DC4BC;font-family: " helvetica="" neue",="" helvetica,="" arial,="" verdana,="" sans-serif;font-size:="" 29px;line-height:="" 150%;"="">`;
    fixedTd = `<td valign="top" class="mcnTextContent" style="padding: 0px 18px 9px;color: #6DC4BC;font-family: helvetica neue, helvetica, arial, verdana, sans-serif;font-size: 29px;line-height: 150%;">`;
    code = code.replace(brokenTd, fixedTd);

    console.log(`Updating ${name}`);

    // Write the template code to disk
    // let filename = `/Users/andy/projects/mandrill-test/${name}.html`;
    // require('fs').writeFileSync(filename, code);

    return mailchimp.templates.update({
        name,
        code: code,
    });
}

// removes all mailchimp preview merge variables from all
// templates in mandrill (since we're using handlebars)
async function fixAllTemplates() {
    const templates = await mailchimp.templates.list();
    await Promise.all(templates.map(fixTemplate));
    const templateList = templates.map(({ name }) => `\n> ${name}`).join('');

    const msg = `*Mandrill Template Fixer:* Updated ${templates.length} templates. ${templateList}`;
    console.log(msg);
    return { statusCode: 200, body: msg };
}

(async () => {
    await fixAllTemplates();
})();

module.exports = { fixAllTemplates };
