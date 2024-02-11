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

/* Добавить контактный номер телефона */
(document.querySelector('#pdf-add-collection'))?.addEventListener('click', addPdf);

function addPdf() {

    /* Получаем прототип формы */
    let newForm = this.dataset.prototype;
    let index = this.dataset.index * 1;
    let call = this.dataset.call * 1;
    let collection = this.dataset.collection;

    //let id = this.dataset.id;

    // newForm = newForm.replace(/__name__/g, name) /* меняем индекс торгового предложения */

    //newForm = newForm.replace(/__call__/g, call)
    newForm = newForm.replace(/__pdf_file__/g, index);

    product_sign_pdf_form_files_0_pdf

    let div = document.createElement('div');
    div.innerHTML = newForm;
    div.id = 'item_product_sign_pdf_form_files_' + index;
    div.classList.add('mb-3');

    let $collection = document.getElementById(collection);
    $collection.append(div);

    /* Удаляем контактный номер телефона */
    (div.querySelector('.del-item-pdf'))?.addEventListener('click', deletePdf);

    this.dataset.index = (index + 1).toString();
}

function deletePdf() {
    document.getElementById('item_'+this.dataset.delete).remove();
}