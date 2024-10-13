/*
 *  Copyright 2024.  Baks.dev <admin@baks.dev>
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 */


executeFunc(function productsSignPdf()
{
    /* Имя формы */
    ChangeProductForm = document.forms.decommission_product_sign_form;

    if(typeof ChangeProductForm === 'undefined')
    {
        return false;
    }

    var object_category = document.getElementById(ChangeProductForm.name + '_category');

    if(object_category === null)
    {
        return false;
    }

    object_category.addEventListener('change', function()
    {
        changeObjectCategory(ChangeProductForm);
    }, false);


    return true;
});

async function changeObjectCategory(forms)
{
    disabledElementsForm(forms);

    document.getElementById('product').classList.add('d-none');
    document.getElementById('offer').classList.add('d-none');
    document.getElementById('variation').classList.add('d-none');
    document.getElementById('modification').classList.add('d-none');

    const data = new FormData(forms);

    let formData = new FormData();
    formData.append(forms.name + '[category]', data.get(forms.name + '[category]'));

    await fetch(forms.action, {
        method: forms.method, // *GET, POST, PUT, DELETE, etc.
        //mode: 'same-origin', // no-cors, *cors, same-origin
        cache: 'no-cache', // *default, no-cache, reload, force-cache, only-if-cached
        credentials: 'same-origin', // include, *same-origin, omit
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },

        redirect: 'follow', // manual, *follow, error
        referrerPolicy: 'no-referrer', // no-referrer, *no-referrer-when-downgrade, origin, origin-when-cross-origin, same-origin, strict-origin, strict-origin-when-cross-origin, unsafe-url
        body: formData // body data type must match "Content-Type" header
    })

        //.then((response) => response)
        .then((response) =>
        {

            if(response.status !== 200)
            {
                return false;
            }

            return response.text();

        })

        .then((data) =>
        {
            if(data)
            {

                var parser = new DOMParser();
                var result = parser.parseFromString(data, 'text/html');

                let preProduct = result.getElementById('product');


                /** Сбрасываем ошибки валидации */
                preProduct.querySelectorAll('.is-invalid').forEach((el) => { el.classList.remove('is-invalid'); });
                preProduct.querySelectorAll('.invalid-feedback').forEach((el) => { el.remove(); });


                preProduct ?
                    document
                        .getElementById('product')
                        .replaceWith(preProduct) :
                    preProduct.innerHTML = '';



                /** SELECT2 */
                let replacer = document.getElementById(forms.name + '_product');
                replacer && replacer.type !== 'hidden' ? preProduct.classList.remove('d-none') : null;

                /** Событие на изменение модификации */
                if(replacer)
                {
                    if(replacer.tagName === 'SELECT')
                    {
                        new NiceSelect(replacer, {searchable: true});

                        let focus = document.getElementById(forms.name + '_product_select2');
                        focus ? focus.click() : null;
                    }
                }

                ///** сбрасываем зависимые поля */
                let preOffer = document.getElementById('offer');
                preOffer ? preOffer.innerHTML = '' : null;
                preOffer ? preOffer.classList.add('d-none') : null;

                ///** сбрасываем зависимые поля */
                let preVariation = document.getElementById('variation');
                preVariation ? preVariation.innerHTML = '' : null;
                preVariation ? preVariation.classList.add('d-none') : null;

                let preModification = document.getElementById('modification');
                preModification ? preModification.innerHTML = '' : null;
                preModification ? preModification.classList.add('d-none') : null;

                if(replacer)
                {
                    replacer.addEventListener('change', function(event)
                    {
                        changeObjectProduct(forms);
                        return false;
                    });
                }
            }

            enableElementsForm(forms);
        });
}

async function changeObjectProduct(forms)
{
    disabledElementsForm(forms);

    document.getElementById('offer').classList.add('d-none');
    document.getElementById('variation').classList.add('d-none');
    document.getElementById('modification').classList.add('d-none');

    //data.delete(forms.name + '[_token]');
    //data.delete(forms.name + '[_offer]');
    //data.delete(forms.name + '[_variation]');
    //data.delete(forms.name + '[_modification]');

    const data = new FormData(forms);
    const formData = new FormData();
    formData.append(forms.name + '[product]', data.get(forms.name + '[product]'));

    await fetch(forms.action, {
        method: forms.method, // *GET, POST, PUT, DELETE, etc.
        //mode: 'same-origin', // no-cors, *cors, same-origin
        cache: 'no-cache', // *default, no-cache, reload, force-cache, only-if-cached
        credentials: 'same-origin', // include, *same-origin, omit
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },

        redirect: 'follow', // manual, *follow, error
        referrerPolicy: 'no-referrer', // no-referrer, *no-referrer-when-downgrade, origin, origin-when-cross-origin, same-origin, strict-origin, strict-origin-when-cross-origin, unsafe-url
        body: formData // body data type must match "Content-Type" header
    })

        //.then((response) => response)
        .then((response) =>
        {

            if(response.status !== 200)
            {
                return false;
            }

            return response.text();

        })

        .then((data) =>
        {

            if(data)
            {

                var parser = new DOMParser();
                var result = parser.parseFromString(data, 'text/html');


                let preOffer = result.getElementById('offer');

                preOffer ? document.getElementById('offer').replaceWith(preOffer) : preOffer.innerHTML = '';

                if(preOffer)
                {

                    /** SELECT2 */

                    let replaceOfferId = forms.name + '_offer';

                    let replacer = document.getElementById(replaceOfferId);
                    replacer && replacer.type !== 'hidden' ? preOffer.classList.remove('d-none') : null;


                    if(replacer.tagName === 'SELECT')
                    {
                        new NiceSelect(replacer, {searchable: true});

                        let focus = document.getElementById(forms.name + '_offer_select2');
                        focus ? focus.click() : null;
                    }

                }


                /** сбрасываем зависимые поля */
                let preVariation = document.getElementById('variation');
                preVariation ? preVariation.innerHTML = '' : null;
                preVariation ? preVariation.classList.add('d-none') : null;

                let preModification = document.getElementById('modification');
                preModification ? preModification.innerHTML = '' : null;
                preModification ? preModification.classList.add('d-none') : null;


                /** Событие на изменение торгового предложения */
                let offerChange = document.getElementById(forms.name + '_offer');

                if(offerChange)
                {

                    offerChange.addEventListener('change', function(event)
                    {
                        changeObjectOffer(forms);
                        return false;
                    });
                }


                // return;
                //
                //
                // /** Изменияем список целевых складов */
                // let warehouse = result.getElementById('targetWarehouse');
                //
                //
                // document.getElementById('targetWarehouse').replaceWith(warehouse);
                // document.getElementById('new_order_form_targetWarehouse').addEventListener('change', changeObjectWarehause, false);
                //
                // new NiceSelect(document.getElementById('new_order_form_targetWarehouse'), {
                //     searchable: true,
                //     id: 'select2-' + replaceId
                // });

            }

            enableElementsForm(forms);
        });
}

async function changeObjectOffer(forms)
{
    disabledElementsForm(forms);

    document.getElementById('variation').classList.add('d-none');
    document.getElementById('modification').classList.add('d-none');

    //const data = new FormData(forms);
    //data.delete(forms.name + '[_token]');
    //data.delete(forms.name + '[_variation]');
    //data.delete(forms.name + '[_modification]');


    const data = new FormData(forms);
    const formData = new FormData();
    formData.append(forms.name + '[offer]', data.get(forms.name + '[offer]'));

    await fetch(forms.action, {
        method: forms.method, // *GET, POST, PUT, DELETE, etc.
        //mode: 'same-origin', // no-cors, *cors, same-origin
        cache: 'no-cache', // *default, no-cache, reload, force-cache, only-if-cached
        credentials: 'same-origin', // include, *same-origin, omit
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },

        redirect: 'follow', // manual, *follow, error
        referrerPolicy: 'no-referrer', // no-referrer, *no-referrer-when-downgrade, origin, origin-when-cross-origin, same-origin, strict-origin, strict-origin-when-cross-origin, unsafe-url
        body: formData // body data type must match "Content-Type" header
    })

        //.then((response) => response)
        .then((response) =>
        {

            if(response.status !== 200)
            {
                return false;
            }

            return response.text();

        })

        .then((data) =>
        {

            if(data)
            {

                var parser = new DOMParser();
                var result = parser.parseFromString(data, 'text/html');


                let preVariation = result.getElementById('variation');

                if(preVariation)
                {

                    document.getElementById('variation').replaceWith(preVariation);

                    /** SELECT2 */

                    let replacer = document.getElementById(forms.name + '_variation');
                    replacer && replacer.type !== 'hidden' ? preVariation.classList.remove('d-none') : null;

                    if(replacer)
                    {

                        if(replacer.tagName === 'SELECT')
                        {
                            new NiceSelect(replacer, {searchable: true});

                            let focus = document.getElementById(forms.name + '_variation_select2');
                            focus ? focus.click() : null;

                            replacer.addEventListener('change', function(event)
                            {
                                changeObjectVariation(forms);
                                return false;
                            });

                        }
                    }

                }

                let preModification = document.getElementById('modification');
                preModification ? preModification.innerHTML = '' : null;
                preModification ? preModification.classList.add('d-none') : null;


            }

            enableElementsForm(forms);
        });
}

async function changeObjectVariation(forms)
{

    disabledElementsForm(forms);

    document.getElementById('modification').classList.add('d-none');

    //const data = new FormData(forms);
    //data.delete(forms.name + '[_token]');
    //data.delete(forms.name + '[_modification]');

    const data = new FormData(forms);
    const formData = new FormData();
    formData.append(forms.name + '[variation]', data.get(forms.name + '[variation]'));


    await fetch(forms.action, {
        method: forms.method, // *GET, POST, PUT, DELETE, etc.
        //mode: 'same-origin', // no-cors, *cors, same-origin
        cache: 'no-cache', // *default, no-cache, reload, force-cache, only-if-cached
        credentials: 'same-origin', // include, *same-origin, omit
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },

        redirect: 'follow', // manual, *follow, error
        referrerPolicy: 'no-referrer', // no-referrer, *no-referrer-when-downgrade, origin, origin-when-cross-origin, same-origin, strict-origin, strict-origin-when-cross-origin, unsafe-url
        body: formData // body data type must match "Content-Type" header
    })

        //.then((response) => response)
        .then((response) =>
        {

            if(response.status !== 200)
            {
                return false;
            }

            return response.text();

        })

        .then((data) =>
        {

            if(data)
            {

                var parser = new DOMParser();
                var result = parser.parseFromString(data, 'text/html');

                let preModification = result.getElementById('modification');


                if(preModification)
                {

                    document.getElementById('modification').replaceWith(preModification);

                    /** SELECT2 */
                    let replacer = document.getElementById(forms.name + '_modification');
                    replacer && replacer.type !== 'hidden' ? preModification.classList.remove('d-none') : null;

                    console.log(replacer && replacer.type !== 'hidden');

                    /** Событие на изменение модификации */
                    if(replacer)
                    {
                        if(replacer.tagName === 'SELECT')
                        {
                            new NiceSelect(replacer, {searchable: true});

                            let focus = document.getElementById(forms.name + '_modification_select2');
                            focus ? focus.click() : null;

                            //replacer.addEventListener('change', function(event)
                            //{
                            //    selectTotal(this)
                            //    return false;
                            //});

                        }
                    }
                }
            }

            enableElementsForm(forms);
        });
}





function _____changeObjectProduct()
{

    let replaceId = ChangeProductForm.name + '_offer';


    /* Создаём объект класса XMLHttpRequest */
    const requestModalName = new XMLHttpRequest();
    requestModalName.responseType = "document";

    (select_product = this).classList.add('disabled');
    (select2_product = document.getElementById(this.id + '_select2'))?.classList.add('disabled');


    /** Блокируем submit */
    btn_product_sign_pdf = document.getElementById(ChangeProductForm.name + '_product_sign_pdf');
    btn_product_sign_pdf?.classList.add('disabled');
    btn_product_sign_pdf?.querySelector('.spinner-border')?.classList.remove('d-none');


    /* Имя формы */
    //let purchaseForm = document.forms.purchase_product_stock_form;


    let formData = new FormData();
    formData.append(this.getAttribute('name'), this.value);

    requestModalName.open(ChangeProductForm.getAttribute('method'), ChangeProductForm.getAttribute('action'), true);

    /* Указываем заголовки для сервера */
    requestModalName.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    /* Получаем ответ от сервера на запрос*/
    requestModalName.addEventListener("readystatechange", function()
    {
        /* request.readyState - возвращает текущее состояние объекта XHR(XMLHttpRequest) */
        if(requestModalName.readyState === 4 && requestModalName.status === 200)
        {

            let result = requestModalName.response.getElementById('offer');


            document.getElementById('offer').replaceWith(result);

            let replacer = document.getElementById(replaceId);

            if(replacer.tagName === 'SELECT')
            {
                new NiceSelect(replacer, {searchable: true, id: 'select2-' + replaceId});

                /** Событие на изменение торгового предложения */
                let offerChange = document.getElementById(ChangeProductForm.name + '_offer');

                if(offerChange)
                {
                    offerChange.addEventListener('change', changeObjectOffer, false);

                    let focus = document.getElementById(replaceId + '_select2');
                    focus ? focus.click() : null;

                }
            }


        }

        /** Снимаем блоки  */
        select_product.classList.remove('disabled');
        select2_product?.classList.remove('disabled');

        btn_product_sign_pdf?.classList.remove('disabled');
        btn_product_sign_pdf?.querySelector('.spinner-border')?.classList.add('d-none');

        return false;
    });

    requestModalName.send(formData);
}


function ___________changeObjectOffer()
{

    select_product.classList.add('disabled');
    select2_product?.classList.add('disabled');

    (select_offer = this).classList.add('disabled');
    (select2_offer = document.getElementById(this.id + '_select2'))?.classList.add('disabled');

    /** Блокируем submit */
    btn_product_sign_pdf = document.getElementById(ChangeProductForm.name + '_product_sign_pdf');
    btn_product_sign_pdf?.classList.add('disabled');
    btn_product_sign_pdf?.querySelector('.spinner-border')?.classList.remove('d-none');


    let replaceId = ChangeProductForm.name + '_variation';

    /* Создаём объект класса XMLHttpRequest */
    const requestModalName = new XMLHttpRequest();
    requestModalName.responseType = "document";

    /* Имя формы */
    //let purchaseForm = document.forms.purchase_product_stock_form;

    let formData = new FormData();
    formData.append(this.getAttribute('name'), this.value);

    requestModalName.open(ChangeProductForm.getAttribute('method'), ChangeProductForm.getAttribute('action'), true);

    /* Указываем заголовки для сервера */
    requestModalName.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    /* Получаем ответ от сервера на запрос*/
    requestModalName.addEventListener("readystatechange", function()
    {
        /* request.readyState - возвращает текущее состояние объекта XHR(XMLHttpRequest) */
        if(requestModalName.readyState === 4 && requestModalName.status === 200)
        {


            let result = requestModalName.response.getElementById('variation');

            document.getElementById('variation').replaceWith(result);

            let replacer = document.getElementById(replaceId);

            /* Удаляем предыдущий Select2 */
            let select2 = document.getElementById(replaceId + '_select2');

            if(select2)
            {
                select2.remove();
            }

            if(replacer.tagName === 'SELECT')
            {
                new NiceSelect(document.getElementById(replaceId), {searchable: true, id: 'select2-' + replaceId});

                /** Событие на изменение множественного варианта предложения */
                let offerVariation = document.getElementById(ChangeProductForm.name + '_variation');

                if(offerVariation)
                {
                    offerVariation.addEventListener('change', changeObjectVariation, false);
                }

                let focus = document.getElementById(replaceId + '_select2');
                focus ? focus.click() : null;
            }

        }

        select_product.classList.remove('disabled');
        select2_product?.classList.remove('disabled');

        select_offer.classList.remove('disabled');
        select2_offer?.classList.remove('disabled');

        btn_product_sign_pdf?.classList.remove('disabled');
        btn_product_sign_pdf?.querySelector('.spinner-border')?.classList.add('d-none');

        return false;
    });

    requestModalName.send(formData);
}


function _________changeObjectVariation()
{

    select_product.classList.add('disabled');
    select2_product?.classList.add('disabled');

    select_offer.classList.add('disabled');
    select2_offer?.classList.add('disabled');

    (select_variation = this).classList.add('disabled');
    (select2_variation = document.getElementById(this.id + '_select2'))?.classList.add('disabled');


    /** Блокируем submit */
    btn_product_sign_pdf = document.getElementById(ChangeProductForm.name + '_product_sign_pdf');
    btn_product_sign_pdf?.classList.add('disabled');
    btn_product_sign_pdf?.querySelector('.spinner-border')?.classList.remove('d-none');


    let replaceId = ChangeProductForm.name + '_modification';

    /* Создаём объект класса XMLHttpRequest */
    const requestModalName = new XMLHttpRequest();
    requestModalName.responseType = "document";

    /* Имя формы */
    //let purchaseForm = document.forms.purchase_product_stock_form;

    let formData = new FormData();
    formData.append(this.getAttribute('name'), this.value);

    requestModalName.open(ChangeProductForm.getAttribute('method'), ChangeProductForm.getAttribute('action'), true);

    /* Указываем заголовки для сервера */
    requestModalName.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    /* Получаем ответ от сервера на запрос*/
    requestModalName.addEventListener("readystatechange", function()
    {
        /* request.readyState - возвращает текущее состояние объекта XHR(XMLHttpRequest) */
        if(requestModalName.readyState === 4 && requestModalName.status === 200)
        {
            let result = requestModalName.response.getElementById('modification');

            document.getElementById('modification').replaceWith(result);

            let replacer = document.getElementById(replaceId);

            if(replacer.tagName === 'SELECT')
            {
                new NiceSelect(document.getElementById(replaceId), {searchable: true, id: 'select2-' + replaceId});
            }

            let focus = document.getElementById(replaceId + '_select2');
            focus ? focus.click() : null;


            /** Событие на изменение множественного варианта предложения */
            //let offerModification = document.getElementById(ChangeProductForm.name + '_modification');
            //
            //if(offerModification)
            //{
            //
            //    offerModification.addEventListener("change", (event) =>
            //    {
            //        setTimeout(function initBootstrap()
            //        {
            //            document.getElementById(ChangeProductForm.name + '_preTotal').focus();
            //        }, 100);
            //
            //    });
            //}

        }


        select_product.classList.remove('disabled');
        select2_product?.classList.remove('disabled');

        select_offer.classList.remove('disabled');
        select2_offer?.classList.remove('disabled');

        select_variation.classList.remove('disabled');
        select2_variation?.classList.remove('disabled');

        btn_product_sign_pdf?.classList.remove('disabled');
        btn_product_sign_pdf?.querySelector('.spinner-border')?.classList.add('d-none');

        return false;
    });

    requestModalName.send(formData);

}


