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

/*
 *  Copyright 2022.  Baks.dev <admin@baks.dev>
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *   limitations under the License.
 *
 */


/** Добавить лист закупки */

var limit_mBdrnGXdN = 1000;

setTimeout(function init_wUgQTMHU() {

    var object_product = document.getElementById('product_sign_form_code_product');

    if (object_product) {

        object_product.addEventListener('change', changeObjectProduct, false);

        //let $addButtonStock = document.getElementById('product_sign_form_addPurchase');

        //$addButtonStock.addEventListener('click', addProductPurchase, false);

        /* Имя формы */
        let purchaseForm = document.forms.product_sign_form;


        let forms = object_product.closest('form');


        /* событие отправки формы */
        // forms.addEventListener('submit', function (event) {
        //     event.preventDefault();
        //     return false;
        // });

        document.getElementById('product_sign_form_product_sign')
            .addEventListener('click', function (event) {
                if (event.key !== "Enter") {
                    submitModalForm(forms);
                }
                return false;
            });

        return;
    }

    if (limit_mBdrnGXdN > 1000) {
        return;
    }

    limit_mBdrnGXdN = limit_mBdrnGXdN * 2;

    setTimeout(init_wUgQTMHU, limit_mBdrnGXdN);

}, 100);


//modal.addEventListener('shown.bs.modal', function () {

/* Change PRODUCT */
//object_product = document.getElementById('product_sign_form_product');


// if (object_product) {
//     object_product.addEventListener('change', changeObjectProduct, false);
//
//
//     let $addButtonStock = document.getElementById('product_sign_form_addPurchase');
//
//     if ($addButtonStock) {
//         $addButtonStock.addEventListener('click', addProductPurchase, false);
//     }
//
//
// } else {
//     eventEmitter.addEventListener('complete', function ()
//     {
//         let object_product = document.getElementById('product_sign_form_product');
//
//         if (object_product) {
//             object_product.addEventListener('change', changeObjectProduct, false);
//
//
//             let $addButtonStock = document.getElementById('product_sign_form_addPurchase');
//
//             if ($addButtonStock) {
//                 $addButtonStock.addEventListener('click', addProductPurchase, false);
//             }
//         }
//     });
// }

//});


function changeObjectProduct() {

    let replaceId = 'product_sign_form_code_offer';


    /* Создаём объект класса XMLHttpRequest */
    const requestModalName = new XMLHttpRequest();
    requestModalName.responseType = "document";

    /* Имя формы */
    let purchaseForm = document.forms.product_sign_form;
    let formData = new FormData();
    formData.append(this.getAttribute('name'), this.value);

    requestModalName.open(purchaseForm.getAttribute('method'), purchaseForm.getAttribute('action'), true);

    /* Указываем заголовки для сервера */
    requestModalName.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    /* Получаем ответ от сервера на запрос*/
    requestModalName.addEventListener("readystatechange", function () {
        /* request.readyState - возвращает текущее состояние объекта XHR(XMLHttpRequest) */
        if (requestModalName.readyState === 4 && requestModalName.status === 200) {

            let result = requestModalName.response.getElementById('offer');


            document.getElementById('offer').replaceWith(result);

            let replacer = document.getElementById(replaceId);

            if (replacer.tagName === 'SELECT') {
                new NiceSelect(replacer, {searchable: true, id: 'select2-' + replaceId});

                /** Событие на изменение торгового предложения */
                let offerChange = document.getElementById('product_sign_form_code_offer');

                if (offerChange) {
                    offerChange.addEventListener('change', changeObjectOffer, false);

                    let focus = document.getElementById(replaceId + '_select2');
                    focus ? focus.click() : null;

                }
            }


        }

        return false;
    });

    requestModalName.send(formData);
}


function changeObjectOffer() {


    let replaceId = 'product_sign_form_code_variation';

    /* Создаём объект класса XMLHttpRequest */
    const requestModalName = new XMLHttpRequest();
    requestModalName.responseType = "document";

    /* Имя формы */
    let purchaseForm = document.forms.product_sign_form;
    let formData = new FormData();
    formData.append(this.getAttribute('name'), this.value);

    requestModalName.open(purchaseForm.getAttribute('method'), purchaseForm.getAttribute('action'), true);

    /* Указываем заголовки для сервера */
    requestModalName.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    /* Получаем ответ от сервера на запрос*/
    requestModalName.addEventListener("readystatechange", function () {
        /* request.readyState - возвращает текущее состояние объекта XHR(XMLHttpRequest) */
        if (requestModalName.readyState === 4 && requestModalName.status === 200) {


            let result = requestModalName.response.getElementById('variation');

            document.getElementById('variation').replaceWith(result);

            let replacer = document.getElementById(replaceId);

            /* Удаляем предыдущий Select2 */
            let select2 = document.getElementById(replaceId + '_select2');

            if (select2) {
                select2.remove();
            }

            if (replacer.tagName === 'SELECT') {
                new NiceSelect(document.getElementById(replaceId), {searchable: true, id: 'select2-' + replaceId});

                /** Событие на изменение множественного варианта предложения */
                let offerVariation = document.getElementById('product_sign_form_code_variation');

                if (offerVariation) {
                    offerVariation.addEventListener('change', changeObjectVariation, false);
                }

                let focus = document.getElementById(replaceId + '_select2');
                focus ? focus.click() : null;
            }

        }

        return false;
    });

    requestModalName.send(formData);
}


function changeObjectVariation() {

    let replaceId = 'product_sign_form_code_modification';

    /* Создаём объект класса XMLHttpRequest */
    const requestModalName = new XMLHttpRequest();
    requestModalName.responseType = "document";

    /* Имя формы */
    let purchaseForm = document.forms.product_sign_form;
    let formData = new FormData();
    formData.append(this.getAttribute('name'), this.value);

    requestModalName.open(purchaseForm.getAttribute('method'), purchaseForm.getAttribute('action'), true);

    /* Указываем заголовки для сервера */
    requestModalName.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    /* Получаем ответ от сервера на запрос*/
    requestModalName.addEventListener("readystatechange", function () {
        /* request.readyState - возвращает текущее состояние объекта XHR(XMLHttpRequest) */
        if (requestModalName.readyState === 4 && requestModalName.status === 200) {

            let result = requestModalName.response.getElementById('modification');

            document.getElementById('modification').replaceWith(result);

            let replacer = document.getElementById(replaceId);

            if (replacer.tagName === 'SELECT') {
                new NiceSelect(document.getElementById(replaceId), {searchable: true, id: 'select2-' + replaceId});
            }

            let focus = document.getElementById(replaceId + '_select2');
            focus ? focus.click() : null;


            /** Событие на изменение множественного варианта предложения */
            let offerModification = document.getElementById('product_sign_form_code_modification');

            // if (offerModification) {
            //
            //     offerModification.addEventListener("change", (event) => {
            //         setTimeout(function initBootstrap() {
            //             document.getElementById('product_sign_form_preTotal').focus();
            //         }, 100);
            //
            //     });
            // }

        }

        return false;
    });

    requestModalName.send(formData);

}




